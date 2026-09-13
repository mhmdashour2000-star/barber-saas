<?php

namespace App\Whatsapp;

use App\Models\Company;

/** Future authenticated provider adapter resolves its destination before constructing the DTO. */
class CompanyResolver
{
    public function byPhoneNumberId(string $destinationId): Company
    {
        return Company::query()->where('whatsapp_enabled', true)
            ->whereNotNull('whatsapp_phone_number_id')->where('whatsapp_phone_number_id', $destinationId)->firstOrFail();
    }
}
