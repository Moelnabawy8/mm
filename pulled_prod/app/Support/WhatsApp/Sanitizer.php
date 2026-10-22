<?php

namespace App\Support\WhatsApp;

/**
 * Meta template body parameters may not contain newlines, tabs, or more than 4 consecutive
 * spaces — violations come back as error 132007. Real data here already trips this: several
 * rows in `task_comments` contain newlines.
 */
class Sanitizer
{
    public const FALLBACK = '—';

    /**
     * @param  int  $max  hydrated template text has its own length ceiling (error 132005),
     *                    so every parameter is capped well below it.
     */
    public static function param(?string $value, int $max = 300, string $fallback = self::FALLBACK): string
    {
        $v = (string) $value;

        // \x0B \x0C are vertical tab / form feed; U+2028 U+2029 are the Unicode line and
        // paragraph separators, which show up in Arabic text pasted out of Word.
        $v = preg_replace('/[\r\n\t\x0B\x0C\x{2028}\x{2029}]+/u', ' ', $v);

        // Collapsing to a single space satisfies the >4-consecutive-spaces rule and more.
        $v = preg_replace('/\s{2,}/u', ' ', $v);
        $v = trim($v);

        if ($v === '') {
            return $fallback; // Meta rejects empty parameters
        }

        // mb_* throughout: Arabic is multi-byte and substr() would split a codepoint,
        // producing invalid UTF-8 that Graph rejects with a different, confusing error.
        if (mb_strlen($v) > $max) {
            $v = mb_substr($v, 0, $max - 1) . '…';
        }

        return $v;
    }

    /** Human-readable one-liner stored on the outbox row for the log UI. */
    public static function preview(array $params): string
    {
        return self::param(implode(' | ', array_filter($params, 'strlen')), 500);
    }
}
