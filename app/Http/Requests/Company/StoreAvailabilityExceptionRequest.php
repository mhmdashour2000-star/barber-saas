<?php

namespace App\Http\Requests\Company;

use App\Models\AvailabilityException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvailabilityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->isCompanyManager()
            && $this->user()->company?->status !== \App\Models\Company::STATUS_SUSPENDED;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company?->id;

        return [
            'type' => [
                'required',
                'string',
                Rule::in([
                    AvailabilityException::TYPE_COMPANY,
                    AvailabilityException::TYPE_SERVICE,
                    AvailabilityException::TYPE_EMPLOYEE,
                ]),
            ],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(\App\Services\AvailabilityService::TIMEZONE)->toDateString(),
                Rule::unique('availability_exceptions', 'date')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->where('type', $this->input('type'));
                    if ($this->input('type') === 'service') {
                        $query->where('service_id', $this->input('service_id'));
                    } elseif ($this->input('type') === 'employee') {
                        $query->where('employee_id', $this->input('employee_id'));
                    }
                })],
            'is_closed' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
            'service_id' => [
                'nullable',
                'required_if:type,' . AvailabilityException::TYPE_SERVICE,
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'employee_id' => [
                'nullable',
                'required_if:type,' . AvailabilityException::TYPE_EMPLOYEE,
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'windows' => ['nullable', 'array'],
            'windows.*.start_time' => ['required_with:windows.*.end_time', 'date_format:H:i'],
            'windows.*.end_time' => ['required_with:windows.*.start_time', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $isClosed = $this->boolean('is_closed');
            $windows = $this->input('windows', []);

            if (!$isClosed && empty($windows)) {
                $validator->errors()->add('windows', 'Custom operating hours require at least one valid time window.');
                return;
            }

            if (!$isClosed && !empty($windows)) {
                $parsed = [];
                foreach ($windows as $index => $w) {
                    $start = $w['start_time'] ?? null;
                    $end = $w['end_time'] ?? null;

                    if ($start && $end) {
                        if ($start >= $end) {
                            $validator->errors()->add("windows.{$index}", "Start time ({$start}) must be before end time ({$end}).");
                            continue;
                        }
                        $parsed[] = ['start' => $start, 'end' => $end];
                    }
                }

                usort($parsed, fn ($a, $b) => strcmp($a['start'], $b['start']));
                for ($i = 0; $i < count($parsed) - 1; $i++) {
                    if ($parsed[$i]['end'] > $parsed[$i + 1]['start']) {
                        $validator->errors()->add('windows', 'Overlapping time windows are not allowed on the same date.');
                        break;
                    }
                }
            }
        });
    }
}
