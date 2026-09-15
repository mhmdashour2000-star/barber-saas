<?php

namespace App\Services;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CustomerViolation;
use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AppointmentService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_MAX_RETRIES = 10;

    public function __construct(
        protected AvailabilityService $availabilityService,
        protected CustomerRestrictionService $restrictionService,
    ) {}

    /** Generate a candidate; the database unique constraint is the final authority. */
    protected function generateBookingCode(): string
    {
        $code = 'AP-';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Company is trusted server context. Input company_id is never used.
     * starts_at must be a timezone-aware Carbon instant; persistence uses UTC seconds.
     */
    public function create(array $data, Company $company, ?string $actorType = null, ?int $actorId = null): Appointment
    {
        $startsAt = $data['starts_at']->copy()->utc()->startOfSecond();

        for ($attempt = 0; $attempt < self::CODE_MAX_RETRIES; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $company, $startsAt, $actorType, $actorId) {
                    $company = $company->fresh();
                    $this->assertActiveCompany($company);
                    $service = $company->services()->findOrFail($data['service_id']);
                    $employee = $this->resolveAndLockEmployee($company, $service->id, $data['employee_id'] ?? null, $startsAt);

                    // Read the business switch after the employee mutex, independently of
                    // WhatsApp transport. A paused company may still manage existing bookings.
                    $company = Company::whereKey($company->id)->sharedLock()->firstOrFail();
                    $this->assertActiveCompany($company);
                    if (!$company->accepting_new_bookings) {
                        throw new InvalidArgumentException('This salon is not accepting new bookings.');
                    }

                    // All authoritative checks take place after the employee mutex is held.
                    $service = $company->services()->sharedLock()->findOrFail($service->id);
                    $customer = $company->customers()->lockForUpdate()->findOrFail($data['customer_id']);
                    $this->restrictionService->assertCanBook($customer);
                    $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);
                    $this->validateSlot($company, $service, $employee, $startsAt, $endsAt);

                    $appointment = $company->appointments()->create([
                        'customer_id' => $customer->id,
                        'service_id' => $service->id,
                        'employee_id' => $employee->id,
                        'booking_code' => $this->generateBookingCode(),
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
                    $this->recordEvent($appointment, AppointmentEventType::CREATED, $actorType, $actorId, [
                        'booking_code' => $appointment->booking_code,
                        'starts_at' => $startsAt->toIso8601String(),
                        'ends_at' => $endsAt->toIso8601String(),
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                    ]);

                    return $appointment;
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                // Retry the WHOLE transaction, including on an insert-time race. Never
                // swallow unrelated unique failures or leave partial events/audit rows.
                if (!str_contains($exception->getMessage(), 'booking_code')) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Could not allocate a unique appointment booking code.');
    }

    /**
     * Lock candidates in ascending ID order, retaining acquired locks until commit.
     * Eligibility failures try the next candidate; SQL failures propagate.
     */
    protected function resolveAndLockEmployee(Company $company, int $serviceId, ?int $employeeId, Carbon $startsAt): Employee
    {
        if ($employeeId !== null) {
            return $company->employees()->lockForUpdate()->findOrFail($employeeId);
        }

        $candidateIds = $company->employees()
            ->whereHas('services', fn ($query) => $query->where('services.id', $serviceId))
            ->orderBy('id')->pluck('id');
        foreach ($candidateIds as $candidateId) {
            try {
                $employee = $company->employees()->lockForUpdate()->findOrFail($candidateId);
                $service = $company->services()->sharedLock()->findOrFail($serviceId);
                $this->validateSlot($company, $service, $employee, $startsAt, $startsAt->copy()->addMinutes($service->duration_minutes));

                return $employee;
            } catch (ModelNotFoundException|InvalidArgumentException) {
                continue;
            }
        }

        throw new InvalidArgumentException('No eligible employee is available for this slot.');
    }

    public function reschedule(
        Appointment $appointment,
        Company $company,
        Carbon $newStartsAt,
        ?int $newEmployeeId = null,
        ?string $actorType = 'user',
        ?int $actorId = null,
    ): Appointment {
        $newStartsAt = $newStartsAt->copy()->utc()->startOfSecond();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $result = DB::transaction(function () use ($appointment, $company, $newStartsAt, $newEmployeeId, $actorType, $actorId) {
                // This first read only chooses the employee mutex, never validates status.
                $observed = $company->appointments()->findOrFail($appointment->id);
                $targetId = $newEmployeeId ?? $observed->employee_id;
                $employee = $company->employees()->lockForUpdate()->findOrFail($targetId);
                $locked = $this->lockAppointment($appointment, $company);
                if ($newEmployeeId === null && $locked->employee_id !== $targetId) {
                    return null; // Employee changed while waiting: release and choose again.
                }
                $this->assertActiveCompany($locked->company);
                if (!$locked->canBeRescheduled()) {
                    throw new InvalidArgumentException('Only confirmed appointments can be rescheduled.');
                }

                $service = $company->services()->sharedLock()->findOrFail($locked->service_id);
                $customer = $company->customers()->lockForUpdate()->findOrFail($locked->customer_id);
                $this->restrictionService->assertCanBook($customer);
                $newEndsAt = $newStartsAt->copy()->addMinutes($locked->service_duration_minutes_snapshot);
                $this->validateSlot($company, $service, $employee, $newStartsAt, $newEndsAt, $locked->id);

                $metadata = [
                    'old_starts_at' => $locked->starts_at->toIso8601String(),
                    'old_ends_at' => $locked->ends_at->toIso8601String(),
                    'old_employee_id' => $locked->employee_id,
                    'old_employee_name' => $company->employees()->findOrFail($locked->employee_id)->name,
                    'new_starts_at' => $newStartsAt->toIso8601String(),
                    'new_ends_at' => $newEndsAt->toIso8601String(),
                    'new_employee_id' => $employee->id,
                    'new_employee_name' => $employee->name,
                ];
                $locked->update([
                    'employee_id' => $employee->id,
                    'starts_at' => $newStartsAt,
                    'ends_at' => $newEndsAt,
                ]);
                $this->recordEvent($locked, AppointmentEventType::RESCHEDULED, $actorType, $actorId, $metadata);

                return $locked;
            }, 3);
            if ($result !== null) {
                return $result;
            }
        }

        throw new RuntimeException('Appointment changed repeatedly; retry rescheduling.');
    }

    public function cancelByCustomer(Appointment $appointment, Company $company, ?string $actorType = 'customer', ?int $actorId = null): Appointment
    {
        return $this->transition($appointment, $company, AppointmentStatus::CANCELLED_BY_CUSTOMER, $actorType, $actorId);
    }

    public function cancelByCompany(Appointment $appointment, Company $company, ?string $actorType = 'user', ?int $actorId = null): Appointment
    {
        return $this->transition($appointment, $company, AppointmentStatus::CANCELLED_BY_COMPANY, $actorType, $actorId);
    }

    public function markCompleted(Appointment $appointment, Company $company, ?string $actorType = 'user', ?int $actorId = null): Appointment
    {
        return $this->transition($appointment, $company, AppointmentStatus::COMPLETED, $actorType, $actorId);
    }

    public function markNoShow(Appointment $appointment, Company $company, ?string $actorType = 'user', ?int $actorId = null): Appointment
    {
        return $this->transition($appointment, $company, AppointmentStatus::NO_SHOW, $actorType, $actorId);
    }

    protected function transition(Appointment $appointment, Company $company, AppointmentStatus $target, ?string $actorType, ?int $actorId): Appointment
    {
        return DB::transaction(function () use ($appointment, $company, $target, $actorType, $actorId) {
            $locked = $this->lockAppointment($appointment, $company);
            if ($target !== AppointmentStatus::CANCELLED_BY_CUSTOMER) {
                $this->assertActiveCompany($locked->company);
            }
            if (!$locked->status->canTransitionTo($target)) {
                throw new InvalidArgumentException("Cannot transition {$locked->status->value} to {$target->value}.");
            }
            $now = Carbon::now('UTC')->startOfSecond();
            if (in_array($target, [AppointmentStatus::COMPLETED, AppointmentStatus::NO_SHOW], true) && $now->lt($locked->starts_at)) {
                throw new InvalidArgumentException('Cannot complete or mark no-show before appointment start.');
            }
            $timestamp = match ($target) {
                AppointmentStatus::COMPLETED => 'completed_at',
                AppointmentStatus::NO_SHOW => 'no_show_at',
                default => 'cancelled_at',
            };
            $locked->update(['status' => $target, $timestamp => $now]);

            $violation = match ($target) {
                AppointmentStatus::NO_SHOW => CustomerViolation::TYPE_NO_SHOW,
                AppointmentStatus::CANCELLED_BY_CUSTOMER => $now->copy()
                    ->addHours($locked->company->late_cancellation_hours ?? 24)->gte($locked->starts_at)
                        ? CustomerViolation::TYPE_LATE_CANCELLATION : null,
                default => null,
            };
            if ($violation !== null) {
                $customer = $company->customers()->lockForUpdate()->findOrFail($locked->customer_id);
                $this->restrictionService->recordViolation($customer, $violation,
                    "Appointment {$locked->booking_code}: {$target->value}.", $locked->id,
                    $actorType === 'user' ? $actorId : null);
            }
            $this->recordEvent($locked, AppointmentEventType::from($target->value), $actorType, $actorId, [
                'old_status' => AppointmentStatus::CONFIRMED->value,
                'new_status' => $target->value,
                'occurred_at' => $now->toIso8601String(),
                'violation_type' => $violation,
            ]);

            return $locked;
        }, 3);
    }

    protected function lockAppointment(Appointment $appointment, Company $company): Appointment
    {
        $locked = $company->appointments()->lockForUpdate()->findOrFail($appointment->id);
        // Reject corrupt cross-tenant references as well as wrong-tenant appointment IDs.
        $company->customers()->findOrFail($locked->customer_id);
        $company->services()->findOrFail($locked->service_id);
        $company->employees()->findOrFail($locked->employee_id);

        return $locked;
    }

    protected function assertActiveCompany(Company $company): void
    {
        if ($company->status !== Company::STATUS_ACTIVE) {
            throw new InvalidArgumentException('The salon is not currently active.');
        }
    }

    protected function validateSlot(Company $company, Service $service, Employee $employee, Carbon $start, Carbon $end, ?int $excludeId = null): void
    {
        if ($employee->company_id !== $company->id || $service->company_id !== $company->id || !$employee->active || !$service->active) {
            throw new InvalidArgumentException('Employee/service must be active and belong to this company.');
        }
        if (!$service->employees()->where('employees.id', $employee->id)->sharedLock()->first()) {
            throw new InvalidArgumentException('Employee is not assigned to this service.');
        }
        $duration = (int) $start->diffInMinutes($end);
        if (!$this->availabilityService->isEmployeeAvailable($service, $employee, $start, $duration)) {
            throw new InvalidArgumentException('Requested slot is outside availability or booking horizon.');
        }
        // A locking read sees the latest committed rows under MySQL REPEATABLE READ.
        // It supplements, never replaces, the employee-row mutex acquired above.
        $conflict = $company->appointments()->where('employee_id', $employee->id)
            ->whereIn('status', array_map(fn (AppointmentStatus $status) => $status->value, AppointmentStatus::schedulingBlockingStatuses()))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->lockForUpdate()->first();
        if ($conflict !== null) {
            throw new InvalidArgumentException('Employee already has a conflicting appointment.');
        }
    }

    protected function recordEvent(Appointment $appointment, AppointmentEventType $type, ?string $actorType, ?int $actorId, array $metadata): AppointmentEvent
    {
        $event = $appointment->company->appointmentEvents()->create([
            'appointment_id' => $appointment->id,
            'type' => $type,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata,
            'created_at' => Carbon::now('UTC')->startOfSecond(),
        ]);
        AuditLog::record('appointment.'.$type->value,
            "Appointment {$appointment->booking_code}: {$type->value}.",
            $actorType === 'user' ? $actorId : null, $appointment->company_id);

        return $event;
    }
}
