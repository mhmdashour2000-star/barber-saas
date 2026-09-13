<?php

namespace App\Whatsapp\Meta;

use App\Models\Company;

class Configuration
{
    public function token(Company $company): string
    {
        $tokens = config('whatsapp.meta.access_tokens', []);
        $token = is_array($tokens) ? ($tokens[$company->whatsapp_phone_number_id] ?? '') : '';
        return is_string($token) ? $token : '';
    }

    public function complete(Company $company): bool
    {
        return preg_match('/^v\d+\.\d+$/D', (string) config('whatsapp.meta.graph_version'))
            && preg_match('/^\d+$/D', (string) $company->whatsapp_phone_number_id)
            && filled($company->whatsapp_business_account_id) && $this->token($company) !== ''
            && filled(config('whatsapp.meta.app_secret')) && filled(config('whatsapp.meta.verify_token'));
    }
}
