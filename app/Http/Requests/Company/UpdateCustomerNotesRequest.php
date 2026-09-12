<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user || !$user->isCompanyManager() || $user->company?->status === \App\Models\Company::STATUS_SUSPENDED) {
            return false;
        }

        $customerId = $this->route('customer');
        if (!$user->company->customers()->where('id', $customerId)->exists()) {
            abort(404, 'Customer not found.');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
