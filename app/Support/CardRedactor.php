<?php

namespace App\Support;

/**
 * Recursively scrubs anything that could be cardholder data out of a structure
 * before it is ever shown in the admin UI, logged, or exported.
 *
 * PCI rule: card numbers, CVV, expiry and tokens never belong in any UI.
 */
class CardRedactor
{
    /** Keys whose values are always replaced wholesale (case-insensitive). */
    private const SENSITIVE_KEYS = [
        'cardnumber', 'card_number', 'pan', 'cardcode', 'card_cvv', 'cvv', 'cvc',
        'cvv2', 'securitycode', 'expirationdate', 'expmonth', 'expyear', 'card_exp',
        'accountnumber', 'track1', 'track2', 'datavalue', 'datadescriptor',
        'cardname', 'nameonaccount',
    ];

    public static function redact($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && in_array(strtolower($k), self::SENSITIVE_KEYS, true)) {
                    $out[$k] = '[REDACTED]';
                } else {
                    $out[$k] = self::redact($v);
                }
            }
            return $out;
        }

        if (is_object($value)) {
            return self::redact((array) $value);
        }

        if (is_string($value)) {
            return self::maskLoosePan($value);
        }

        return $value;
    }

    /**
     * Mask bare 13–19 digit strings that pass a Luhn check (real card numbers),
     * while leaving transaction IDs etc. untouched.
     */
    private static function maskLoosePan(string $value): string
    {
        return preg_replace_callback('/\b\d{13,19}\b/', function ($m) {
            $digits = $m[0];
            return self::luhnValid($digits) ? '****' . substr($digits, -4) : $digits;
        }, $value);
    }

    private static function luhnValid(string $number): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }
        return $sum % 10 === 0;
    }
}
