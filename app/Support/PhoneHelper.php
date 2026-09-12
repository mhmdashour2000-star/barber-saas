<?php

namespace App\Support;

class PhoneHelper
{
    /**
     * Normalize a phone number to standard E.164 international format (+90XXXXXXXXXX).
     * Handles common Turkish local formats:
     * - 05551112233 -> +905551112233
     * - 5551112233  -> +905551112233
     * - 905551112233 -> +905551112233
     * - +90 555 111 22 33 -> +905551112233
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Strip non-digit characters except leading plus if any
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));

        if (empty($cleaned)) {
            return null;
        }

        // If it starts with '+', remove it temporarily for length inspection
        $digitsOnly = ltrim($cleaned, '+');

        // Turkish 10-digit number without leading 0 or country code: 5XXXXXXXXX
        if (strlen($digitsOnly) === 10 && str_starts_with($digitsOnly, '5')) {
            return '+90' . $digitsOnly;
        }

        // Turkish 11-digit number with leading 0: 05XXXXXXXXX
        if (strlen($digitsOnly) === 11 && str_starts_with($digitsOnly, '05')) {
            return '+90' . substr($digitsOnly, 1);
        }

        // Turkish 12-digit number starting with 90: 905XXXXXXXXX
        if (strlen($digitsOnly) === 12 && str_starts_with($digitsOnly, '90')) {
            return '+' . $digitsOnly;
        }

        // Fallback for general international numbers: ensure single leading '+'
        return '+' . $digitsOnly;
    }
}
