<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user || !$user->isCompanyManager() || $user->company?->status === \App\Models\Company::STATUS_SUSPENDED) {
            return false;
        }

        $company = $user->company;

        if ($this->route('service')) {
            $serviceId = $this->route('service');
            if (!$company->services()->where('id', $serviceId)->exists()) {
                abort(404, 'Service not found.');
            }
        }

        if ($this->route('employee')) {
            $employeeId = $this->route('employee');
            if (!$company->employees()->where('id', $employeeId)->exists()) {
                abort(404, 'Employee not found.');
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array'],
            'days.*' => ['nullable', 'array'],
            'days.*.*.start_time' => ['required_with:days.*.*.end_time', 'date_format:H:i'],
            'days.*.*.end_time' => ['required_with:days.*.*.start_time', 'date_format:H:i'],
        ];
    }

    /**
     * Custom validation to enforce start_time < end_time and prevent overlapping intervals per day.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $days = $this->input('days', []);

            foreach ($days as $day => $windows) {
                if (!is_array($windows)) {
                    continue;
                }

                $validDay = (int) $day;
                if ($validDay < 1 || $validDay > 7) {
                    $validator->errors()->add("days.{$day}", "Invalid day of week {$day}. Must be between 1 and 7.");
                    continue;
                }

                $parsedWindows = [];

                foreach ($windows as $index => $window) {
                    $start = $window['start_time'] ?? null;
                    $end = $window['end_time'] ?? null;

                    if (!$start && !$end) {
                        continue;
                    }

                    if ($start && $end) {
                        if ($start >= $end) {
                            $validator->errors()->add("days.{$day}.{$index}", "Start time ({$start}) must be before end time ({$end}) on day {$day}.");
                            continue;
                        }

                        $parsedWindows[] = [
                            'start' => $start,
                            'end' => $end,
                            'index' => $index,
                        ];
                    }
                }

                // Check for overlaps within the same day
                usort($parsedWindows, fn ($a, $b) => strcmp($a['start'], $b['start']));

                for ($i = 0; $i < count($parsedWindows) - 1; $i++) {
                    $current = $parsedWindows[$i];
                    $next = $parsedWindows[$i + 1];

                    // If current window end is strictly greater than next window start -> overlap
                    if ($current['end'] > $next['start']) {
                        $validator->errors()->add(
                            "days.{$day}",
                            "Overlapping time windows detected on day {$day} between {$current['start']}-{$current['end']} and {$next['start']}-{$next['end']}."
                        );
                        break;
                    }
                }
            }
        });
    }
}
