<?php

namespace App\Support\Telegram;

use Illuminate\Support\Facades\DB;

/**
 * Reads Telegram settings straight from the `settings` key/value table.
 *
 * Same reason as WhatsAppConfig: deliberately NOT App\Models\Utility::settings().
 * That method is declared with zero parameters and falls back to
 * `where('created_by', 1)` when Auth::check() is false — which is every CLI run,
 * i.e. our dispatcher — instead of the company's row set.
 *
 * Writes go through App\Http\Controllers\TelegramController@saveSettings using the
 * same INSERT ... ON DUPLICATE KEY UPDATE idiom as SystemController.
 */
class TelegramConfig
{
    /** The company that owns the projects/tasks module. */
    public const COMPANY_ID = 2;

    public const API_BASE = 'https://api.telegram.org';

    public const DEFAULTS = [
        'telegram_enabled'         => '0',
        'telegram_dry_run'         => '1',   // safe by default: log everything, send nothing
        'telegram_bot_token'       => '',
        'telegram_bot_username'    => '',    // without @, for the t.me link
        'telegram_daily_cap'       => '300',
        'telegram_paused_until'    => '',
        'telegram_timezone'        => 'Asia/Amman',
        'telegram_notify_assign'   => '1',
        'telegram_notify_stage'    => '0',
        'telegram_notify_comment'  => '0',
        'telegram_notify_due'      => '0',
        'telegram_due_offsets'     => 'd1',  // comma list of d1 (tomorrow) and/or d0 (today)
        'telegram_update_offset'   => '0',   // getUpdates offset, advanced by telegram:dispatch
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = DB::table('settings')
            ->where('created_by', self::COMPANY_ID)
            ->whereIn('name', array_keys(self::DEFAULTS))
            ->pluck('value', 'name')
            ->toArray();

        return self::$cache = array_merge(self::DEFAULTS, $rows);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function enabled(): bool
    {
        return self::get('telegram_enabled') == '1';
    }

    public static function dryRun(): bool
    {
        return self::get('telegram_dry_run') == '1';
    }

    /**
     * Per-event toggle. `assign` is the feature's whole point, so it defaults on;
     * the noisier ones default off and are opted into from the settings page.
     */
    public static function eventEnabled(string $event): bool
    {
        return match ($event) {
            'assign'  => self::get('telegram_notify_assign') == '1',
            'stage'   => self::get('telegram_notify_stage') == '1',
            'comment' => self::get('telegram_notify_comment') == '1',
            'due'     => self::get('telegram_notify_due') == '1',
            'test'    => true,
            default   => false,
        };
    }

    public static function botToken(): string
    {
        return trim((string) self::get('telegram_bot_token'));
    }

    public static function botUsername(): string
    {
        return ltrim(trim((string) self::get('telegram_bot_username')), '@');
    }

    public static function dailyCap(): int
    {
        return max(1, (int) self::get('telegram_daily_cap'));
    }

    /**
     * The company timezone. The OS runs UTC and the company operates in Asia/Amman; the
     * app only applies the company timezone inside the XSS middleware when Auth::check()
     * is true, so every CLI run would otherwise be 3 hours off.
     */
    public static function timezone(): string
    {
        return trim((string) self::get('telegram_timezone')) ?: 'Asia/Amman';
    }

    /** @return string[] e.g. ['d1'] */
    public static function dueOffsets(): array
    {
        $raw = (string) self::get('telegram_due_offsets');
        $out = array_values(array_intersect(
            array_map('trim', explode(',', $raw)),
            ['d0', 'd1']
        ));

        return $out ?: ['d1'];
    }

    public static function pausedUntil(): ?\Carbon\Carbon
    {
        $v = trim((string) self::get('telegram_paused_until'));
        if ($v === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function updateOffset(): int
    {
        return max(0, (int) self::get('telegram_update_offset'));
    }

    /** Used by the dispatcher (401 pause, getUpdates offset) and the settings screen. */
    public static function put(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        DB::insert(
            'INSERT INTO settings (`value`, `name`, `created_by`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = VALUES(`updated_at`)',
            [$value, $key, self::COMPANY_ID, $now, $now]
        );
        self::flush();
    }

    public static function configured(): bool
    {
        return self::botToken() !== '';
    }
}
