<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->isCompanyManager()
            && $this->user()->company?->status !== \App\Models\Company::STATUS_SUSPENDED;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('whatsapp_phone_number') && is_string($this->input('whatsapp_phone_number'))) {
            $this->merge(['whatsapp_phone_number' => \App\Support\PhoneHelper::normalize($this->input('whatsapp_phone_number'))]);
        }
    }

    public function rules(): array
    {
        return [
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'accepting_new_bookings' => ['sometimes', 'boolean'],
            'whatsapp_phone_number' => ['nullable', 'required_if:whatsapp_enabled,1', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/D',
                \Illuminate\Validation\Rule::unique('companies', 'whatsapp_phone_number')->ignore($this->user()->company?->id)],
            'whatsapp_phone_number_id' => ['prohibited'],
            'whatsapp_business_account_id' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'booking_days_ahead' => ['required', 'integer', 'min:1', 'max:90'],
            'late_cancellation_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'violation_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'block_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }
}
