<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\DB;

/**
 * Reads WhatsApp settings straight from the `settings` key/value table.
 *
 * Deliberately does NOT go through App\Models\Utility::settings(). That method is declared
 * with zero parameters (every caller passes creatorId() and PHP discards it), and when
 * Auth::check() is false — which is every CLI run, i.e. our dispatcher and reminder cron —
 * it falls back to `where('created_by', 1)`, the super-admin row set, not the company's.
 *
 * Writes go through App\Http\Controllers\WhatsAppController@saveSettings using the same
 * INSERT ... ON DUPLICATE KEY UPDATE idiom as SystemController@saveTwilioSettings.
 */
class WhatsAppConfig
{
    /** The company that owns the projects/tasks module. */
    public const COMPANY_ID = 2;

    public const GRAPH_BASE   = 'https://graph.facebook.com';
    public const API_VERSION  = 'v21.0';

    public const DEFAULTS = [
        'whatsapp_enabled'            => '0',
        'whatsapp_dry_run'            => '1',   // safe by default: log everything, send nothing
        'whatsapp_token'              => '',
        'whatsapp_phone_number_id'    => '',
        'whatsapp_waba_id'            => '',
        'whatsapp_template_name'      => 'task_alert_ar',
        'whatsapp_language'           => 'ar',
        'whatsapp_default_country'    => '962',
        'whatsapp_daily_cap'          => '300',
        'whatsapp_paused_until'       => '',
        'whatsapp_api_version'        => self::API_VERSION,
        'whatsapp_timezone'           => 'Asia/Amman',
        'whatsapp_notify_assign'      => '1',
        'whatsapp_notify_stage'       => '0',
        'whatsapp_notify_comment'     => '0',
        'whatsapp_notify_due'         => '0',
        'whatsapp_due_offsets'        => 'd1',  // comma list of d1 (tomorrow) and/or d0 (today)
        'whatsapp_webhook_verify_token' => '',  // must match the Verify Token entered in Meta's App Dashboard
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
        return self::get('whatsapp_enabled') == '1';
    }

    public static function dryRun(): bool
    {
        return self::get('whatsapp_dry_run') == '1';
    }

    public static function webhookVerifyToken(): string
    {
        return (string) self::get('whatsapp_webhook_verify_token');
    }

    /**
     * Per-event toggle. `assign` is the feature's whole point, so it defaults on;
     * the noisier ones default off and are opted into from the settings page.
     */
    public static function eventEnabled(string $event): bool
    {
        return match ($event) {
            'assign'  => self::get('whatsapp_notify_assign') == '1',
            'stage'   => self::get('whatsapp_notify_stage') == '1',
            'comment' => self::get('whatsapp_notify_comment') == '1',
            'due'     => self::get('whatsapp_notify_due') == '1',
            'test'    => true,
            default   => false,
        };
    }

    public static function token(): string
    {
        return trim((string) self::get('whatsapp_token'));
    }

    public static function phoneNumberId(): string
    {
        return trim((string) self::get('whatsapp_phone_number_id'));
    }

    public static function wabaId(): string
    {
        return trim((string) self::get('whatsapp_waba_id'));
    }

    public static function template(): string
    {
        return trim((string) self::get('whatsapp_template_name')) ?: 'task_alert_ar';
    }

    public static function language(): string
    {
        return trim((string) self::get('whatsapp_language')) ?: 'ar';
    }

    public static function apiVersion(): string
    {
        return trim((string) self::get('whatsapp_api_version')) ?: self::API_VERSION;
    }

    public static function defaultCountry(): string
    {
        return preg_replace('/\D/', '', (string) self::get('whatsapp_default_country')) ?: '962';
    }

    public static function dailyCap(): int
    {
        return max(1, (int) self::get('whatsapp_daily_cap'));
    }

    /**
     * The company timezone. The OS runs UTC and the company operates in Asia/Amman; the
     * app only applies the company timezone inside the XSS middleware when Auth::check()
     * is true, so every CLI run would otherwise be 3 hours off.
     */
    public static function timezone(): string
    {
        return trim((string) self::get('whatsapp_timezone')) ?: 'Asia/Amman';
    }

    /** @return string[] e.g. ['d1'] */
    public static function dueOffsets(): array
    {
        $raw = (string) self::get('whatsapp_due_offsets');
        $out = array_values(array_intersect(
            array_map('trim', explode(',', $raw)),
            ['d0', 'd1']
        ));

        return $out ?: ['d1'];
    }

    public static function pausedUntil(): ?\Carbon\Carbon
    {
        $v = trim((string) self::get('whatsapp_paused_until'));
        if ($v === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($v);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Used by the dispatcher on error 190 / 368 to stop the whole run. */
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
        return self::token() !== '' && self::phoneNumberId() !== '';
    }
}
