<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class TemporaryEmployeeCredentials
{
    public static function encrypt(Request $request, array $credentials): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $request->user()->id,
            'company_id' => $request->user()->company->id,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'credentials' => $credentials,
        ], JSON_THROW_ON_ERROR));
    }

    public static function consume(Request $request): ?array
    {
        // Pull before decrypting; legacy plaintext flash values are discarded.
        $ciphertext = $request->session()->pull('new_employee_credentials');
        if (!is_string($ciphertext)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            return null;
        }

        if (($payload['user_id'] ?? null) !== $request->user()->id
            || ($payload['company_id'] ?? null) !== $request->user()->company->id
            || ($payload['expires_at'] ?? 0) <= now()->timestamp) {
            return null;
        }

        // Atomic, non-secret receipt prevents replay from concurrent/stale session saves.
        if (!Cache::add('employee-credentials-consumed:'.hash('sha256', $ciphertext), true,
            $payload['expires_at'] - now()->timestamp)) {
            return null;
        }

        return $payload['credentials'] ?? null;
    }
}
