<?php

namespace App\Support\WhatsApp;

/**
 * Turns the messy numbers this ERP actually holds into E.164.
 *
 * `employees.phone` (the import source) allows several numbers separated by commas and
 * contains real junk: "+971501234567" appears 8 times and "+1234567890" 4 times as
 * placeholder rows.
 *
 * There is no reliable way to check whether a number has WhatsApp before sending — the old
 * `contacts` endpoint is gone. The only ground truth is a real send returning 131026, which
 * is why the per-row test button in the recipients screen matters.
 */
class PhoneNormalizer
{
    /** Placeholder numbers seen in the live data; never send to these. */
    public const BLOCKLIST = [
        '1234567890',
        '971501234567',
        '12345678901',
        '0000000000',
    ];

    /**
     * @return string|null canonical "+9627..." or null when nothing usable was found
     */
    public static function e164(?string $raw, ?string $defaultCountry = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $defaultCountry = $defaultCountry ?: WhatsAppConfig::defaultCountry();

        // The UI explicitly allows "multiple numbers, separate with comma" — take the first
        // candidate that actually validates rather than the first token.
        foreach (preg_split('/[,;\/|]+/', $raw) as $candidate) {
            $n = self::normalizeOne($candidate, $defaultCountry);
            if ($n !== null) {
                return $n;
            }
        }

        return null;
    }

    /** Same value without the leading '+' — the shape Graph's `to` field expects. */
    public static function graph(?string $raw, ?string $defaultCountry = null): ?string
    {
        $e164 = self::e164($raw, $defaultCountry);

        return $e164 === null ? null : ltrim($e164, '+');
    }

    private static function normalizeOne(string $candidate, string $defaultCountry): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        // Arabic-Indic digits occasionally arrive from copy/paste.
        $candidate = strtr($candidate, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $hasPlus = str_starts_with($candidate, '+');
        $digits  = preg_replace('/\D/', '', $candidate);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits  = substr($digits, 2);
            $hasPlus = true;
        }

        // A bare national number: drop the trunk zero and prepend the default country.
        if (!$hasPlus && str_starts_with($digits, '0')) {
            $digits = $defaultCountry . ltrim($digits, '0');
        } elseif (!$hasPlus && strlen($digits) <= 10) {
            // 7-10 digits with no country code at all — assume the default country.
            $digits = $defaultCountry . $digits;
        }

        if (!self::plausible($digits)) {
            return null;
        }

        return '+' . $digits;
    }

    private static function plausible(string $digits): bool
    {
        $len = strlen($digits);
        if ($len < 8 || $len > 15) {
            return false; // E.164 hard limits
        }

        if (in_array($digits, self::BLOCKLIST, true)) {
            return false;
        }

        // "+00000000000", "+11111111111", ... — never a real subscriber.
        if (preg_match('/^(\d)\1+$/', $digits)) {
            return false;
        }

        // Sequential dummies like 1234567890 wherever they appear as the whole number.
        if (str_contains($digits, '1234567890')) {
            return false;
        }

        return true;
    }

    /** For display in the recipients table. */
    public static function pretty(?string $raw): string
    {
        $e164 = self::e164($raw);

        return $e164 ?? (trim((string) $raw) === '' ? '' : trim((string) $raw));
    }
}
