<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\ScheduleChangeNeedsConfirmation;
use App\Models\{Appointment, Company};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvailabilityChangeService
{
    public function __construct(private AvailabilityService $availability) {}

    public function apply(Request $request, Company $company, array $data, \Closure $change): void
    {
        DB::transaction(function () use ($request, $company, $data, $change) {
            // Same employee-before-appointment order as booking/rescheduling. Company-wide
            // locking also serializes service and company closure changes with bookings.
            $company->employees()->orderBy('id')->lockForUpdate()->get(['id']);
            $bookings = $company->appointments()->where('status', AppointmentStatus::CONFIRMED)
                ->where('ends_at', '>', now())->orderBy('id')->lockForUpdate()->get();
            $before = $bookings->mapWithKeys(fn ($booking) => [$booking->id => $this->fits($company, $booking)]);
            $change();
            $affected = $bookings->filter(fn ($booking) => $before[$booking->id] && !$this->fits($company, $booking));
            if ($affected->isEmpty()) return;

            $identity = $affected->map(fn ($booking) => [$booking->id, $booking->employee_id,
                $booking->starts_at->toIso8601String(), $booking->ends_at->toIso8601String()])->values()->all();
            $binding = json_encode([$company->id, $request->user()->id, $request->path(), $request->method(), http_build_query($data), $identity]);
            $provided = $request->input('change_confirmation');
            if (is_string($provided) && preg_match('/^(\d+)\.([a-f0-9]{64})$/D', $provided, $match)
                && (int) $match[1] > now()->timestamp && (int) $match[1] <= now()->addMinutes(15)->timestamp
                && hash_equals(hash_hmac('sha256', $binding.'|'.$match[1], config('app.key')), $match[2])) {
                return;
            }
            $expires = now()->addMinutes(15)->timestamp;
            $confirmation = $expires.'.'.hash_hmac('sha256', $binding.'|'.$expires, config('app.key'));
            $fields = $this->fields($data);
            if (array_key_exists('days', $data)) $fields['schedule_submitted'] = '1';
            $affected->each(fn ($booking) => $booking->setRelation('employee', $company->employees()->find($booking->employee_id)));
            // Roll back ONLY the proposed availability mutation. Nothing is committed until
            // the manager acknowledges this exact input and current affected booking set.
            throw new ScheduleChangeNeedsConfirmation($affected, $fields, $confirmation);
        }, 3);
    }

    private function fits(Company $company, Appointment $appointment): bool
    {
        $service = $company->services()->findOrFail($appointment->service_id);
        $employee = $company->employees()->findOrFail($appointment->employee_id);
        $start = $appointment->starts_at->copy()->setTimezone(AvailabilityService::TIMEZONE);
        $end = $appointment->ends_at->copy()->setTimezone(AvailabilityService::TIMEZONE);
        if (!$start->isSameDay($end)) return false;
        // Historical confirmed appointments need window evaluation even beyond the current
        // booking horizon. New-booking eligibility/conflict rules remain untouched.
        $windows = $this->availability->intersectWindowsList(
            $this->availability->getEffectiveServiceWindows($service, $start, true),
            $this->availability->getEffectiveEmployeeWindows($employee, $start),
        );
        foreach ($windows as $window) {
            if ($window['start'].':00' <= $start->format('H:i:s') && $window['end'].':00' >= $end->format('H:i:s')) return true;
        }
        return false;
    }

    private function fields(array $data, string $prefix = ''): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? $key : $prefix.'['.$key.']';
            if (is_array($value)) $fields += $this->fields($value, $name);
            elseif ($value !== null) $fields[$name] = is_bool($value) ? (string) (int) $value : (string) $value;
        }
        return $fields;
    }
}
