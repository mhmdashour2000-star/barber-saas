<?php

namespace App\Http\Requests\Company;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user || !$user->isCompanyManager()) {
            return false;
        }
        $company = $user->company;
        abort_unless($company, 404, 'Company not found.');
        abort_unless($company->appointments()->whereKey($this->route('appointment'))->exists(), 404);

        return $company->status !== Company::STATUS_SUSPENDED;
    }

    public function rules(): array
    {
        $rules = [
            // Local wall-clock input, explicitly interpreted as Europe/Istanbul by the controller.
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s,Y-m-d H:i:s'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')
                ->where(fn ($query) => $query->where('company_id', $this->user()->company->id))],
        ];

        foreach (['company_id', 'service_id', 'customer_id', 'status', 'ends_at', 'booking_code',
            'service_name_snapshot', 'service_duration_minutes_snapshot', 'service_price_minor_units_snapshot',
            'employee_name_snapshot', 'customer_name_snapshot', 'customer_phone_snapshot'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
