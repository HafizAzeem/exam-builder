<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalize a Pakistan mobile / WhatsApp number to 11 digits (03XXXXXXXXX).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '92')) {
            $digits = '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '0'.$digits;
        }

        return $digits !== '' ? $digits : null;
    }
}
