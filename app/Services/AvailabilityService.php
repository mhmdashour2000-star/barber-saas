<?php

namespace App\Services;

use App\Models\AvailabilityException;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const TIMEZONE = 'Europe/Istanbul';

    /** Read-only suggestions; AppointmentService remains the authoritative locked booking boundary. */
    public function getBookableSlots(Service $service, Carbon $day, ?int $employeeId = null): array
    {
        if (!$this->isDateWithinHorizon($service->company, $day)) {
            return [];
        }
        $step = max(1, (int) config('whatsapp.slot_interval_minutes', 15));
        $employees = $service->employees()->where('employees.company_id', $service->company_id)
            ->where('employees.active', true)->when($employeeId !== null, fn ($q) => $q->where('employees.id', $employeeId))
            ->orderBy('employees.id')->get();
        $date = $day->copy()->setTimezone(self::TIMEZONE)->toDateString();
        $slots = [];
        foreach ($employees as $employee) {
            $bookings = $service->company->appointments()->where('employee_id', $employee->id)
                ->where('status', \App\Enums\AppointmentStatus::CONFIRMED)
                ->where('starts_at', '<', Carbon::parse($date, self::TIMEZONE)->addDay()->utc())
                ->where('ends_at', '>', Carbon::parse($date, self::TIMEZONE)->utc())->get(['starts_at', 'ends_at']);
            foreach ($this->getCombinedServiceEmployeeWindows($service, $employee, $day) as $window) {
                $cursor = Carbon::parse($date.' '.$window['start'], self::TIMEZONE);
                $until = Carbon::parse($date.' '.$window['end'], self::TIMEZONE);
                while ($cursor->copy()->addMinutes($service->duration_minutes)->lte($until)) {
                    $end = $cursor->copy()->addMinutes($service->duration_minutes);
                    if ($cursor->gte(Carbon::now(self::TIMEZONE))
                        && !$bookings->contains(fn ($booking) => $booking->starts_at->lt($end) && $booking->ends_at->gt($cursor))) {
                        $slots[$cursor->format('H:i')] = $cursor->format('H:i');
                    }
                    $cursor->addMinutes($step);
                }
            }
        }
        ksort($slots);

        return array_values($slots);
    }

    /**
     * Determine if a date is strictly within the company's booking horizon.
     */
    public function isDateWithinHorizon(Company $company, Carbon $targetDate): bool
    {
        $today = Carbon::now(self::TIMEZONE)->startOfDay();
        $checkDate = $targetDate->copy()->setTimezone(self::TIMEZONE)->startOfDay();

        // Cannot book in the past
        if ($checkDate->lt($today)) {
            return false;
        }

        $horizonDays = $company->booking_days_ahead ?? 7;
        $maxDate = $today->copy()->addDays($horizonDays)->endOfDay();

        return $checkDate->lte($maxDate);
    }

    /**
     * Get the active working intervals [start, end] in 'H:i' for a Service on a given date.
     * Evaluates Date Exceptions first, then falls back to Weekly Availability.
     * Returns empty array if closed or unavailable.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function getEffectiveServiceWindows(Service $service, Carbon $targetDate, bool $ignoreBookingHorizon = false): array
    {
        $company = $service->company;

        // 1. Company Status must be operational
        if ($company->status !== Company::STATUS_ACTIVE) {
            return [];
        }

        // 2. Service must be active
        if (!$service->active) {
            return [];
        }

        // 3. Date must be within horizon
        if (!$ignoreBookingHorizon && !$this->isDateWithinHorizon($company, $targetDate)) {
            return [];
        }

        $dateString = $targetDate->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d');

        // 4. Check Salon-Wide (Company) Date Exception
        $companyException = $company->availabilityExceptions()
            ->where('type', AvailabilityException::TYPE_COMPANY)
            ->whereDate('date', $dateString)
            ->with(['windows' => fn ($query) => $query->orderBy('start_time')->orderBy('id')])
            // Fail closed for legacy duplicates; otherwise newest record wins deterministically.
            ->orderByDesc('is_closed')->orderByDesc('id')->first();

        if ($companyException) {
            if ($companyException->is_closed) {
                return []; // Salon completely closed on this date
            }
            // Salon has custom hours on this date; these restrict salon operational limits
            $companyWindows = $companyException->windows->map(fn ($w) => [
                'start' => substr($w->start_time, 0, 5),
                'end' => substr($w->end_time, 0, 5),
            ])->toArray();

            if (empty($companyWindows)) {
                return [];
            }
        }

        // 5. Check Service-Specific Date Exception
        $serviceException = $service->availabilityExceptions()
            ->where('company_id', $company->id)->where('type', AvailabilityException::TYPE_SERVICE)
            ->whereDate('date', $dateString)
            ->with(['windows' => fn ($query) => $query->orderBy('start_time')->orderBy('id')])
            // Fail closed for legacy duplicates; otherwise newest record wins deterministically.
            ->orderByDesc('is_closed')->orderByDesc('id')->first();

        if ($serviceException) {
            if ($serviceException->is_closed) {
                return []; // Service closed on this date
            }
            $serviceWindows = $serviceException->windows->map(fn ($w) => [
                'start' => substr($w->start_time, 0, 5),
                'end' => substr($w->end_time, 0, 5),
            ])->toArray();
        } else {
            // Fallback to Service Weekly Availability
            $isoDayOfWeek = $targetDate->copy()->setTimezone(self::TIMEZONE)->isoWeekday(); // 1=Mon .. 7=Sun
            $serviceWindows = $service->weeklyAvailabilities()
                ->where('day_of_week', $isoDayOfWeek)
                ->orderBy('start_time')
                ->get()
                ->map(fn ($w) => [
                    'start' => substr($w->start_time, 0, 5),
                    'end' => substr($w->end_time, 0, 5),
                ])->toArray();
        }

        if (empty($serviceWindows)) {
            return [];
        }

        // If company exception has custom hours, intersect with service windows
        if (isset($companyWindows)) {
            return $this->intersectWindowsList($serviceWindows, $companyWindows);
        }

        return $serviceWindows;
    }

    /**
     * Get the active working intervals [start, end] in 'H:i' for an Employee on a given date.
     * Evaluates Date Exceptions first, then falls back to Weekly Availability.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function getEffectiveEmployeeWindows(Employee $employee, Carbon $targetDate): array
    {
        $company = $employee->company;

        // 1. Employee must be active
        if (!$employee->active) {
            return [];
        }

        // 2. Company must be active
        if ($company->status !== Company::STATUS_ACTIVE) {
            return [];
        }

        $dateString = $targetDate->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d');

        // Check Salon-Wide (Company) Date Exception
        $companyException = $company->availabilityExceptions()
            ->where('type', AvailabilityException::TYPE_COMPANY)
            ->whereDate('date', $dateString)
            ->with(['windows' => fn ($query) => $query->orderBy('start_time')->orderBy('id')])
            // Fail closed for legacy duplicates; otherwise newest record wins deterministically.
            ->orderByDesc('is_closed')->orderByDesc('id')->first();

        if ($companyException) {
            if ($companyException->is_closed) {
                return [];
            }
            $companyWindows = $companyException->windows->map(fn ($w) => [
                'start' => substr($w->start_time, 0, 5),
                'end' => substr($w->end_time, 0, 5),
            ])->toArray();
        }

        // Check Employee-Specific Date Exception
        $employeeException = $employee->availabilityExceptions()
            ->where('company_id', $company->id)->where('type', AvailabilityException::TYPE_EMPLOYEE)
            ->whereDate('date', $dateString)
            ->with(['windows' => fn ($query) => $query->orderBy('start_time')->orderBy('id')])
            // Fail closed for legacy duplicates; otherwise newest record wins deterministically.
            ->orderByDesc('is_closed')->orderByDesc('id')->first();

        if ($employeeException) {
            if ($employeeException->is_closed) {
                return []; // Employee off/sick/leave on this date
            }
            $employeeWindows = $employeeException->windows->map(fn ($w) => [
                'start' => substr($w->start_time, 0, 5),
                'end' => substr($w->end_time, 0, 5),
            ])->toArray();
        } else {
            // Fallback to Employee Weekly Availability
            $isoDayOfWeek = $targetDate->copy()->setTimezone(self::TIMEZONE)->isoWeekday();
            $employeeWindows = $employee->weeklyAvailabilities()
                ->where('day_of_week', $isoDayOfWeek)
                ->orderBy('start_time')
                ->get()
                ->map(fn ($w) => [
                    'start' => substr($w->start_time, 0, 5),
                    'end' => substr($w->end_time, 0, 5),
                ])->toArray();
        }

        if (empty($employeeWindows)) {
            return [];
        }

        if (isset($companyWindows)) {
            return $this->intersectWindowsList($employeeWindows, $companyWindows);
        }

        return $employeeWindows;
    }

    /**
     * Compute intersection between Service windows and Employee windows on a given date.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function getCombinedServiceEmployeeWindows(Service $service, Employee $employee, Carbon $targetDate): array
    {
        // 1. Employee must be assigned to Service
        if (!$service->employees()->where('employees.id', $employee->id)->exists()) {
            return [];
        }

        $serviceWindows = $this->getEffectiveServiceWindows($service, $targetDate);
        if (empty($serviceWindows)) {
            return [];
        }

        $employeeWindows = $this->getEffectiveEmployeeWindows($employee, $targetDate);
        if (empty($employeeWindows)) {
            return [];
        }

        return $this->intersectWindowsList($serviceWindows, $employeeWindows);
    }

    /**
     * Check if an employee is available for a service starting at a specific datetime.
     * The FULL service duration must fit within the combined available windows.
     */
    public function isEmployeeAvailable(Service $service, Employee $employee, Carbon $startAt, ?int $durationMinutes = null): bool
    {
        $startInTz = $startAt->copy()->setTimezone(self::TIMEZONE);
        $durationMinutes ??= $service->duration_minutes;
        if ($durationMinutes <= 0 || $service->company_id !== $employee->company_id) {
            return false;
        }
        $endInTz = $startInTz->copy()->addMinutes($durationMinutes);

        // Disallow start if in the past
        if ($startInTz->lt(Carbon::now(self::TIMEZONE))) {
            return false;
        }

        $windows = $this->getCombinedServiceEmployeeWindows($service, $employee, $startInTz);
        if (empty($windows)) {
            return false;
        }

        $startTimeStr = $startInTz->format('H:i:s');
        $endTimeStr = $endInTz->format('H:i:s');

        // If end time crossed midnight on same appointment, not allowed in regular day windows
        if ($endInTz->format('Y-m-d') !== $startInTz->format('Y-m-d')) {
            return false;
        }

        foreach ($windows as $window) {
            // Full duration must fit inside window: window.start <= start AND window.end >= end
            if ($window['start'].':00' <= $startTimeStr && $window['end'].':00' >= $endTimeStr) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve all eligible active employees available for a service at a specific datetime.
     * Foundation for both "Specific Barber" and "Any Available Barber".
     *
     * @return Collection<int, Employee>
     */
    public function getAvailableEmployees(Service $service, Carbon $startAt): Collection
    {
        // Only consider active employees assigned to this service
        $assignedEmployees = $service->employees()
            ->where('employees.active', true)
            ->get();

        return $assignedEmployees->filter(function (Employee $employee) use ($service, $startAt) {
            return $this->isEmployeeAvailable($service, $employee, $startAt);
        })->values();
    }

    /**
     * Intersect two lists of [start, end] windows in 'H:i' format.
     *
     * @param array<int, array{start: string, end: string}> $listA
     * @param array<int, array{start: string, end: string}> $listB
     * @return array<int, array{start: string, end: string}>
     */
    public function intersectWindowsList(array $listA, array $listB): array
    {
        $result = [];

        foreach ($listA as $a) {
            foreach ($listB as $b) {
                $overlapStart = max($a['start'], $b['start']);
                $overlapEnd = min($a['end'], $b['end']);

                if ($overlapStart < $overlapEnd) {
                    $result[] = [
                        'start' => $overlapStart,
                        'end' => $overlapEnd,
                    ];
                }
            }
        }

        // Sort and merge adjacent/overlapping if needed
        usort($result, fn ($x, $y) => strcmp($x['start'], $y['start']));

        return $result;
    }
}
