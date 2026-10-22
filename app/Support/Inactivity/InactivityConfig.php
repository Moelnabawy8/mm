<?php

namespace App\Support\Inactivity;

use Illuminate\Support\Facades\DB;

/**
 * Reads the inactivity-ban settings straight from the `settings` key/value table.
 *
 * Same reasoning as App\Support\WhatsApp\WhatsAppConfig: Utility::settings() falls back to
 * `where('created_by', 1)` whenever Auth::check() is false — which is every cron run — and
 * would hand us the super-admin row set instead of the company's. The weekly job runs from
 * cron, so it must never depend on an authenticated user.
 */
class InactivityConfig
{
    /** The company that owns the HR module. */
    public const COMPANY_ID = 2;

    public const DEFAULTS = [
        // Master switch. '0' = report only (compute + report, ban nobody). '1' = actually ban.
        // Ships as '0' on purpose: 65 of 112 accounts match the rule on day one.
        'inactivity_enforce'        => '0',
        'inactivity_threshold_days' => '7',
        'inactivity_grace_days'     => '14',
        'inactivity_exempt_roles'   => 'company,super admin,ceo,CAO,CSO,CMO,Manager,client,Ticket system,for-testing',
        'inactivity_exempt_users'   => '',
        'inactivity_report_emails'  => 'admin@my-communication.uk',
        'inactivity_last_run_at'    => '',

        // نافذة السماح بعد فكّ الحظر: يحتاج المستخدم أيامًا ليسجّل دخولًا فعليًّا
        // ويتقدّم last_login_at من تلقاء نفسه، وإلا أعاد التشغيل التالي حظره.
        'inactivity_unban_grace_days' => '7',

        // مسار الموبايل (Sanctum) لا يكتب last_login_at إطلاقًا، فبدون هذا الإشارة
        // يظهر مستخدمو التطبيق النشطون كغائبين منذ شهور. راجع InactivityAudit::rows().
        'inactivity_use_token_activity' => '1',

        // حدّ أقصى لعدد المحظورين في التشغيلة الواحدة. لو تجاوزه المرشّحون توقّف
        // التشغيلة بلا حظر — صمّام أمان ضد خطأ في الإعدادات يقفل الشركة كلها.
        'inactivity_max_bans_per_run' => '10',

        // قناة إرسال التقرير، مستقلّة عن إعدادات mail_* العامة عمدًا.
        //
        // بيانات اعتماد SMTP المخزّنة لا تعمل: notifications@ (في settings) و marketing@
        // (في .env) كلتاهما تصل إلى AUTH LOGIN ثم يقطع الخادم الاتصال بعد 15 ثانية.
        // عطل قائم قبل هذه الميزة ويصيب بريد النظام كله — فشل تأكيد التذاكر مسجَّل في
        // اللوج بنفس الخطأ. التصحيح الحقيقي هو إصلاح كلمة مرور الصندوق.
        //
        // 'sendmail' = تسليم مباشر للـ MTA المحلي (لا TLS ولا مصادقة)، وهو الوحيد الذي
        // ينجح اليوم. 'smtp' يستخدم المضيف والمنفذ أدناه. '' يعود للإعدادات العامة.
        'inactivity_mail_transport'  => 'sendmail',
        'inactivity_mail_host'       => 'localhost',
        'inactivity_mail_port'       => '25',
        'inactivity_mail_encryption' => '',
        'inactivity_mail_username'   => '',
        'inactivity_mail_password'   => '',
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

    /** True only when the owner has explicitly turned enforcement on. */
    public static function enforcing(): bool
    {
        return self::get('inactivity_enforce') === '1';
    }

    public static function thresholdDays(): int
    {
        return max(1, (int) self::get('inactivity_threshold_days'));
    }

    public static function graceDays(): int
    {
        return max(0, (int) self::get('inactivity_grace_days'));
    }

    public static function unbanGraceDays(): int
    {
        return max(1, (int) self::get('inactivity_unban_grace_days'));
    }

    public static function useTokenActivity(): bool
    {
        return self::get('inactivity_use_token_activity') === '1';
    }

    public static function maxBansPerRun(): int
    {
        return max(1, (int) self::get('inactivity_max_bans_per_run'));
    }

    /** @return string[] role/type names that are never auto-banned */
    public static function exemptRoles(): array
    {
        return self::csv(self::get('inactivity_exempt_roles'));
    }

    /** @return int[] user ids the admin pinned as permanently exempt */
    public static function exemptUsers(): array
    {
        return array_values(array_filter(array_map(
            'intval',
            self::csv(self::get('inactivity_exempt_users'))
        )));
    }

    /** @return string[] */
    public static function reportEmails(): array
    {
        return array_values(array_filter(
            self::csv(self::get('inactivity_report_emails')),
            static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /**
     * Point the mailer at the company's own SMTP row set before sending.
     *
     * Utility::sendEmailTemplate() does the same override, but it dereferences Auth::user()
     * with no null check and therefore fatals under cron — so the weekly job repeats just
     * the config part here and sends through a plain Mailable instead.
     *
     * @return string the resolved From address
     */
    public static function applyMailConfig(): string
    {
        $rows = DB::table('settings')
            ->where('created_by', self::COMPANY_ID)
            ->where('name', 'LIKE', 'mail_%')
            ->pluck('value', 'name')
            ->toArray();

        $pick = static fn (string $key, $fallback) => (isset($rows[$key]) && trim((string) $rows[$key]) !== '')
            ? $rows[$key]
            : $fallback;

        $from = $pick('mail_from_address', config('mail.from.address'));

        // قناة خاصة بالتقرير لها الأولوية على إعدادات mail_* العامة — انظر التعليق على
        // inactivity_mail_host في DEFAULTS. لا نعدّل الإعدادات العامة كي لا نغيّر سلوك
        // بقية بريد النظام.
        $transport = trim((string) self::get('inactivity_mail_transport'));

        if ($transport === 'sendmail') {
            config([
                'mail.default'                  => 'sendmail',
                'mail.mailers.sendmail.transport' => 'sendmail',
                'mail.from.address'             => $from,
                'mail.from.name'                => $pick('mail_from_name', config('mail.from.name')),
            ]);

            return $from;
        }

        $ownHost = trim((string) self::get('inactivity_mail_host'));

        if ($transport === 'smtp' && $ownHost !== '') {
            config([
                'mail.default'                 => 'smtp',
                'mail.mailers.smtp.transport'  => 'smtp',
                'mail.mailers.smtp.host'       => $ownHost,
                'mail.mailers.smtp.port'       => (int) self::get('inactivity_mail_port'),
                'mail.mailers.smtp.encryption' => self::get('inactivity_mail_encryption') ?: null,
                'mail.mailers.smtp.username'   => self::get('inactivity_mail_username') ?: null,
                'mail.mailers.smtp.password'   => self::get('inactivity_mail_password') ?: null,
                'mail.from.address'            => $from,
                'mail.from.name'               => $pick('mail_from_name', config('mail.from.name')),
            ]);

            return $from;
        }

        config([
            'mail.mailers.smtp.transport'  => $pick('mail_driver', config('mail.mailers.smtp.transport')),
            'mail.mailers.smtp.host'       => $pick('mail_host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port'       => $pick('mail_port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.encryption' => $pick('mail_encryption', config('mail.mailers.smtp.encryption')),
            'mail.mailers.smtp.username'   => $pick('mail_username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password'   => $pick('mail_password', config('mail.mailers.smtp.password')),
            'mail.from.address'            => $from,
            'mail.from.name'               => $pick('mail_from_name', config('mail.from.name')),
        ]);

        return $from;
    }

    /** @return string[] */
    private static function csv(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }
}
