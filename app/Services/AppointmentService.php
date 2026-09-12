<?php

namespace App\Services;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerViolation;
use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AppointmentService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected CustomerRestrictionService $restrictionService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Booking Code Generation
    |--------------------------------------------------------------------------
    | Format: AP-XXXXXXXX (8 uppercase alphanumeric characters).
    | Excludes confusing characters: O, 0, I, 1.
    | Retries safely on collision (globally unique, lookups always tenant-scoped).
    */

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 8;
    private const CODE_MAX_RETRIES = 10;

    protected function generateBookingCode(): string
    {
        for ($attempt = 0; $attempt < self::CODE_MAX_RETRIES; $attempt++) {
            $code = 'AP-';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }

            if (!Appointment::where('booking_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Failed to generate unique booking code after ' . self::CODE_MAX_RETRIES . ' attempts.');
    }

    /*
    |--------------------------------------------------------------------------
    | Appointment Creation
    |--------------------------------------------------------------------------
    | Validates all prerequisites, then acquires an employee-row lock inside a
    | DB transaction before re-checking availability and conflicts. This ensures
    | that concurrent booking attempts for the same employee are serialized.
    */

    /**
     * Create a new appointment.
     *
     * @param array{
     *     customer_id: int,
     *     service_id: int,
     *     employee_id: int|null,
     *     starts_at: Carbon
     * } $data
     * @param Company $company
     * @param string|null $actorType  e.g. 'user', 'customer', 'system'
     * @param int|null    $actorId
     *
     * @throws InvalidArgumentException  On validation failure
     * @throws RuntimeException          On conflict / unavailability
     */
    public function create(array $data, Company $company, ?string $actorType = null, ?int $actorId = null): Appointment
    {
        // ── Pre-transaction validation (read-only) ──────────────────────

        // 1. Company must be active
        if ($company->status !== Company::STATUS_ACTIVE) {
            throw new InvalidArgumentException('The salon is not currently active.');
        }

        // 2. Resolve and validate customer
        /** @var Customer $customer */
        $customer = $company->customers()->findOrFail($data['customer_id']);
        if (!$customer->active) {
            throw new InvalidArgumentException('This customer account is inactive.');
        }
        if ($customer->isBlocked()) {
            throw new InvalidArgumentException('This customer is currently blocked and cannot book appointments.');
        }

        // 3. Resolve and validate service
        /** @var Service $service */
        $service = $company->services()->findOrFail($data['service_id']);
        if (!$service->active) {
            throw new InvalidArgumentException('This service is currently inactive.');
        }

        // 4. Calculate times
        $startsAt = $data['starts_at']->copy();
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        // 5. Resolve employee (specific or any-available)
        $employeeId = $data['employee_id'] ?? null;

        if ($employeeId) {
            // Specific barber — pre-validate before transaction
            $this->validateSpecificEmployee($company, $service, $employeeId, $startsAt);
        }

        // ── Transaction with employee row lock ──────────────────────────

        return DB::transaction(function () use ($company, $customer, $service, $startsAt, $endsAt, $employeeId, $actorType, $actorId) {

            $employee = $this->resolveAndLockEmployee($company, $service, $startsAt, $endsAt, $employeeId);

            // Generate booking code
            $bookingCode = $this->generateBookingCode();

            // Create appointment with snapshots
            /** @var Appointment $appointment */
            $appointment = $company->appointments()->create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'employee_id' => $employee->id,
                'booking_code' => $bookingCode,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::CONFIRMED,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'service_name_snapshot' => $service->name,
                'service_price_minor_units_snapshot' => $service->price_minor_units,
                'service_duration_minutes_snapshot' => $service->duration_minutes,
                'employee_name_snapshot' => $employee->name,
            ]);

            // Record event
            $this->recordEvent($appointment, AppointmentEventType::CREATED, $actorType, $actorId, [
                'booking_code' => $bookingCode,
                'service' => $service->name,
                'employee' => $employee->name,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ]);

            // Audit log
            AuditLog::record(
                'appointment.created',
                "Appointment {$bookingCode} created for customer '{$customer->name}' with '{$employee->name}' on {$startsAt->format('Y-m-d H:i')}.",
                $actorType === 'user' ? $actorId : null,
                $company->id
            );

            return $appointment;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Rescheduling
    |--------------------------------------------------------------------------
    */

    /**
     * Reschedule a confirmed appointment to a new time and/or employee.
     */
    public function reschedule(
        Appointment $appointment,
        Carbon $newStartsAt,
        ?int $newEmployeeId = null,
        ?string $actorType = 'user',
        ?int $actorId = null
    ): Appointment {
        $company = $appointment->company;

        if ($company->status !== Company::STATUS_ACTIVE) {
            throw new InvalidArgumentException('The salon is not currently active.');
        }

        if (!$appointment->canBeRescheduled()) {
            throw new InvalidArgumentException('This appointment cannot be rescheduled because its status is terminal.');
        }

        // Cannot reschedule to the past
        if ($newStartsAt->lt(Carbon::now(AvailabilityService::TIMEZONE))) {
            throw new InvalidArgumentException('Cannot reschedule to a past time.');
        }

        $service = $appointment->service;
        $newEndsAt = $newStartsAt->copy()->addMinutes($service->duration_minutes);

        $oldStartsAt = $appointment->starts_at->copy();
        $oldEndsAt = $appointment->ends_at->copy();
        $oldEmployeeId = $appointment->employee_id;
        $oldEmployeeName = $appointment->employee->name;

        $targetEmployeeId = $newEmployeeId ?? $appointment->employee_id;

        return DB::transaction(function () use ($appointment, $company, $service, $newStartsAt, $newEndsAt, $targetEmployeeId, $oldStartsAt, $oldEndsAt, $oldEmployeeId, $oldEmployeeName, $actorType, $actorId) {

            $employee = $this->resolveAndLockEmployee($company, $service, $newStartsAt, $newEndsAt, $targetEmployeeId, $appointment->id);

            $appointment->update([
                'employee_id' => $employee->id,
                'starts_at' => $newStartsAt,
                'ends_at' => $newEndsAt,
            ]);

            $this->recordEvent($appointment, AppointmentEventType::RESCHEDULED, $actorType, $actorId, [
                'old_starts_at' => $oldStartsAt->toIso8601String(),
                'old_ends_at' => $oldEndsAt->toIso8601String(),
                'old_employee_id' => $oldEmployeeId,
                'old_employee_name' => $oldEmployeeName,
                'new_starts_at' => $newStartsAt->toIso8601String(),
                'new_ends_at' => $newEndsAt->toIso8601String(),
                'new_employee_id' => $employee->id,
                'new_employee_name' => $employee->name,
            ]);

            AuditLog::record(
                'appointment.rescheduled',
                "Appointment {$appointment->booking_code} rescheduled from {$oldStartsAt->format('Y-m-d H:i')} to {$newStartsAt->format('Y-m-d H:i')}.",
                $actorType === 'user' ? $actorId : null,
                $company->id
            );

            return $appointment->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cancellation
    |--------------------------------------------------------------------------
    */

    /**
     * Cancel appointment by customer.
     * Evaluates late cancellation boundary and may create a customer violation.
     */
    public function cancelByCustomer(
        Appointment $appointment,
        ?string $actorType = 'customer',
        ?int $actorId = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $actorType, $actorId) {
            $company = $appointment->company;

            $this->assertStatusTransition($appointment, AppointmentStatus::CANCELLED_BY_CUSTOMER);

            $now = Carbon::now();
            $appointment->update([
                'status' => AppointmentStatus::CANCELLED_BY_CUSTOMER,
                'cancelled_at' => $now,
            ]);

            $this->recordEvent($appointment, AppointmentEventType::CANCELLED_BY_CUSTOMER, $actorType, $actorId);

            // Evaluate late cancellation boundary
            $lateCancellationHours = $company->late_cancellation_hours ?? 24;
            $hoursUntilStart = $now->diffInMinutes($appointment->starts_at, false) / 60;

            // <= threshold hours means late cancellation (violation)
            if ($hoursUntilStart <= $lateCancellationHours) {
                $this->restrictionService->recordViolation(
                    $appointment->customer,
                    CustomerViolation::TYPE_LATE_CANCELLATION,
                    "Late cancellation of appointment {$appointment->booking_code}.",
                    $appointment->id,
                    $actorType === 'user' ? $actorId : null
                );
            }

            AuditLog::record(
                'appointment.cancelled_by_customer',
                "Appointment {$appointment->booking_code} cancelled by customer.",
                $actorType === 'user' ? $actorId : null,
                $company->id
            );

            return $appointment->fresh();
        });
    }

    /**
     * Cancel appointment by the company/manager. No customer violation created.
     */
    public function cancelByCompany(
        Appointment $appointment,
        ?string $actorType = 'user',
        ?int $actorId = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $actorType, $actorId) {
            $company = $appointment->company;

            if ($company->status !== Company::STATUS_ACTIVE) {
                throw new InvalidArgumentException('The salon is not currently active.');
            }

            $this->assertStatusTransition($appointment, AppointmentStatus::CANCELLED_BY_COMPANY);

            $appointment->update([
                'status' => AppointmentStatus::CANCELLED_BY_COMPANY,
                'cancelled_at' => Carbon::now(),
            ]);

            $this->recordEvent($appointment, AppointmentEventType::CANCELLED_BY_COMPANY, $actorType, $actorId);

            AuditLog::record(
                'appointment.cancelled_by_company',
                "Appointment {$appointment->booking_code} cancelled by company.",
                $actorType === 'user' ? $actorId : null,
                $company->id
            );

            return $appointment->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Completion
    |--------------------------------------------------------------------------
    */

    /**
     * Mark appointment as completed. Cannot be done before appointment start time.
     */
    public function markCompleted(
        Appointment $appointment,
        ?string $actorType = 'user',
        ?int $actorId = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $actorType, $actorId) {
            $company = $appointment->company;

            if ($company->status !== Company::STATUS_ACTIVE) {
                throw new InvalidArgumentException('The salon is not currently active.');
            }

            $this->assertStatusTransition($appointment, AppointmentStatus::COMPLETED);

            // Cannot mark completed before appointment start time
            if (Carbon::now()->lt($appointment->starts_at)) {
                throw new InvalidArgumentException('Cannot mark an appointment as completed before its start time.');
            }

            $appointment->update([
                'status' => AppointmentStatus::COMPLETED,
                'completed_at' => Carbon::now(),
            ]);

            $this->recordEvent($appointment, AppointmentEventType::COMPLETED, $actorType, $actorId);

            AuditLog::record(
                'appointment.completed',
                "Appointment {$appointment->booking_code} marked as completed.",
                $actorType === 'user' ? $actorId : null,
                $company->id
            );

            return $appointment->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | No-Show
    |--------------------------------------------------------------------------
    */

    /**
     * Mark appointment as no-show. Creates a customer violation.
     * Cannot be done before appointment start time.
     */
    public function markNoShow(
        Appointment $appointment,
        ?string $actorType = 'user',
        ?int $actorId = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $actorType, $actorId) {
            $company = $appointment->company;

            if ($company->status !== Company::STATUS_ACTIVE) {
                throw new InvalidArgumentException('The salon is not currently active.');
            }

            $this->assertStatusTransition($appointment, AppointmentStatus::NO_SHOW);

            // Cannot mark no-show before appointment start time
            if (Carbon::now()->lt($appointment->starts_at)) {
                throw new InvalidArgumentException('Cannot mark an appointment as no-show before its start time.');
            }

            $appointment->update([
                'status' => AppointmentStatus::NO_SHOW,
                'no_show_at' => Carbon::now(),
            ]);

            $this->recordEvent($appointment, AppointmentEventType::NO_SHOW, $actorType, $actorId);

            // Record customer violation
            $this->restrictionService->recordViolation(
                $appointment->customer,
                CustomerViolation::TYPE_NO_SHOW,
                "No-show for appointment {$appointment->booking_code}.",
                $appointment->id,
                $actorType === 'user' ? $actorId : null
            );

            AuditLog::record(
                'appointment.no_show',
                "Appointment {$appointment->booking_code} marked as no-show.",
                $actorType === 'user' ? $actorId : null,
                $company->id
            );

            return $appointment->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Validate a specific employee can serve this appointment (pre-transaction read-only).
     */
    protected function validateSpecificEmployee(Company $company, Service $service, int $employeeId, Carbon $startsAt): void
    {
        /** @var Employee $employee */
        $employee = $company->employees()->findOrFail($employeeId);

        if (!$employee->active) {
            throw new InvalidArgumentException("Employee '{$employee->name}' is currently inactive.");
        }

        if (!$service->employees()->where('employees.id', $employee->id)->exists()) {
            throw new InvalidArgumentException("Employee '{$employee->name}' is not assigned to service '{$service->name}'.");
        }

        if (!$this->availabilityService->isEmployeeAvailable($service, $employee, $startsAt)) {
            throw new InvalidArgumentException("Employee '{$employee->name}' is not available at the requested time.");
        }
    }

    /**
     * Resolve the employee inside the transaction, acquire a row lock, then re-check
     * all eligibility and conflict conditions.
     *
     * CONCURRENCY PROTECTION:
     * ─────────────────────────
     * Every booking attempt for a given employee MUST lock the employee row first
     * via SELECT … FOR UPDATE. Because only one transaction can hold the lock at a
     * time, concurrent booking attempts for the same employee are serialized.
     *
     * After acquiring the lock the method re-validates:
     *   1. Employee is still active and belongs to the company
     *   2. Employee is assigned to the requested service
     *   3. Employee schedule/availability still allows the time slot
     *   4. No conflicting confirmed appointment exists for this employee
     *
     * For "any available barber" mode (employeeId = null) the method iterates
     * eligible candidates (lowest ID first) and tries each one, skipping any that
     * turn out to be conflicting after lock acquisition. This prevents blindly
     * failing when the first candidate was claimed by a concurrent request.
     *
     * @param int|null $excludeAppointmentId  Appointment ID to exclude from conflict check (for rescheduling)
     */
    protected function resolveAndLockEmployee(
        Company $company,
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $employeeId = null,
        ?int $excludeAppointmentId = null
    ): Employee {

        if ($employeeId !== null) {
            // ── Specific barber ──
            return $this->lockAndValidateEmployee($company, $service, $startsAt, $endsAt, $employeeId, $excludeAppointmentId);
        }

        // ── Any available barber ──
        // Get candidates sorted by lowest ID (deterministic)
        $candidates = $this->availabilityService->getAvailableEmployees($service, $startsAt)
            ->sortBy('id')
            ->values();

        if ($candidates->isEmpty()) {
            throw new InvalidArgumentException('No available employee found for this service and time slot.');
        }

        $lastException = null;
        foreach ($candidates as $candidate) {
            try {
                return $this->lockAndValidateEmployee($company, $service, $startsAt, $endsAt, $candidate->id, $excludeAppointmentId);
            } catch (InvalidArgumentException|RuntimeException $e) {
                $lastException = $e;
                continue; // Try next candidate
            }
        }

        throw new InvalidArgumentException(
            'No available employee could be confirmed for this time slot. ' .
            ($lastException ? $lastException->getMessage() : '')
        );
    }

    /**
     * Lock a specific employee row and validate all conditions inside the transaction.
     */
    protected function lockAndValidateEmployee(
        Company $company,
        Service $service,
        Carbon $startsAt,
        Carbon $endsAt,
        int $employeeId,
        ?int $excludeAppointmentId = null
    ): Employee {
        // 1. Lock the employee row — serializes concurrent bookings for this employee
        /** @var Employee $employee */
        $employee = Employee::where('id', $employeeId)
            ->where('company_id', $company->id)
            ->lockForUpdate()
            ->firstOrFail();

        // 2. Re-validate active
        if (!$employee->active) {
            throw new InvalidArgumentException("Employee '{$employee->name}' is currently inactive.");
        }

        // 3. Re-validate service assignment
        if (!$service->employees()->where('employees.id', $employee->id)->exists()) {
            throw new InvalidArgumentException("Employee '{$employee->name}' is not assigned to service '{$service->name}'.");
        }

        // 4. Re-validate schedule availability
        if (!$this->availabilityService->isEmployeeAvailable($service, $employee, $startsAt)) {
            throw new InvalidArgumentException("Employee '{$employee->name}' is not available at the requested time.");
        }

        // 5. Check for conflicting appointments
        $this->assertNoConflict($employee, $startsAt, $endsAt, $excludeAppointmentId);

        return $employee;
    }

    /**
     * Assert no overlapping confirmed appointment exists for this employee.
     *
     * Overlap rule: new_start < existing_end AND new_end > existing_start
     */
    protected function assertNoConflict(
        Employee $employee,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeAppointmentId = null
    ): void {
        $blockingStatuses = array_map(
            fn (AppointmentStatus $s) => $s->value,
            AppointmentStatus::schedulingBlockingStatuses()
        );

        $query = Appointment::where('employee_id', $employee->id)
            ->whereIn('status', $blockingStatuses)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("Employee '{$employee->name}' already has a conflicting appointment at this time.");
        }
    }

    /**
     * Assert that the status transition is valid.
     */
    protected function assertStatusTransition(Appointment $appointment, AppointmentStatus $target): void
    {
        if (!$appointment->status->canTransitionTo($target)) {
            throw new InvalidArgumentException(
                "Cannot transition appointment from '{$appointment->status->value}' to '{$target->value}'."
            );
        }
    }

    /**
     * Record an immutable appointment event.
     */
    protected function recordEvent(
        Appointment $appointment,
        AppointmentEventType $type,
        ?string $actorType = null,
        ?int $actorId = null,
        ?array $metadata = null
    ): AppointmentEvent {
        return AppointmentEvent::create([
            'appointment_id' => $appointment->id,
            'company_id' => $appointment->company_id,
            'type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'created_at' => Carbon::now(),
        ]);
    }
}
