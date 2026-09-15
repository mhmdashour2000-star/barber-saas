<?php

namespace App\Http\Requests\Company;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class ResetEmployeePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCompanyManager()
            && $this->user()->company()->where('status', Company::STATUS_ACTIVE)->exists();
    }

    public function rules(): array
    {
        return ['password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']];
    }
}
