<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Support\WhatsApp\WhatsAppConfig;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for "is the WhatsApp connection actually working?".
 *
 * Shared by the `whatsapp:doctor` command and the Test Connection button in the settings
 * screen, so the CLI and the UI can never disagree about the state of the integration.
 *
 * Every Graph check reads the token AS STORED IN THE DATABASE. That is deliberate:
 * app/Http/Middleware/FilterRequest.php strips the literal substrings 'alert' and
 * 'javascript' from every string in every web request, so a token could in principle be
 * saved truncated. Validating the stored value catches that immediately instead of at 3am
 * as a baffling error 190.
 */
class ConnectionDoctor
{
    public const PASS = 'pass';
    public const WARN = 'warn';
    public const FAIL = 'fail';
    public const INFO = 'info';

    /** Must match the {{n}} count in the approved template body. */
    public const EXPECTED_PARAMS = 6;

    private array $results = [];
    private bool $remoteOnly;

    /**
     * @param bool $remoteOnly skip the local cron/outbox checks — meaningful for the
     *                         browser-side button, where shell_exec sees the web user's
     *                         crontab rather than the site user's.
     */
    public function __construct(bool $remoteOnly = false)
    {
        $this->remoteOnly = $remoteOnly;
    }

    /** @return array<int, array{level:string,name:string,detail:string}> */
    public function run(): array
    {
        $this->results = [];
        WhatsAppConfig::flush();

        $this->checkConfig();

        if (WhatsAppConfig::configured()) {
            $this->checkToken();
            $this->checkPhoneNumber();
            $this->checkHealth();
            $this->checkTemplate();
            $this->checkWabaOwnsNumber();
        }

        if (!$this->remoteOnly) {
            $this->checkCron();
        }

        $this->checkOutbox();
        $this->checkRecipients();

        return $this->results;
    }

    public function summary(): array
    {
        $fail = count(array_filter($this->results, fn ($r) => $r['level'] === self::FAIL));
        $warn = count(array_filter($this->results, fn ($r) => $r['level'] === self::WARN));

        return [
            'failed'    => $fail,
            'warnings'  => $warn,
            'connected' => $fail === 0 && WhatsAppConfig::configured(),
        ];
    }

    /* ---------------------------------------------------------------- checks */

    private function checkConfig(): void
    {
        $missing = [];
        foreach (['whatsapp_token' => __('Access Token'), 'whatsapp_phone_number_id' => __('Phone Number ID')] as $key => $label) {
            if (trim((string) WhatsAppConfig::get($key)) === '') {
                $missing[] = $label;
            }
        }

        if ($missing) {
            $this->add(self::FAIL, __('Configuration'), __('Missing:') . ' ' . implode(', ', $missing));

            return;
        }

        $this->add(self::PASS, __('Configuration'), sprintf(
            'phone_number_id=%s · waba=%s · template=%s · lang=%s',
            WhatsAppConfig::phoneNumberId(),
            WhatsAppConfig::wabaId() ?: '—',
            WhatsAppConfig::template(),
            WhatsAppConfig::language()
        ));

        $this->add(
            WhatsAppConfig::enabled() ? self::PASS : self::WARN,
            __('Master switch'),
            WhatsAppConfig::enabled() ? __('Enabled') : __('DISABLED — nothing will be sent')
        );

        $this->add(
            WhatsAppConfig::dryRun() ? self::WARN : self::PASS,
            __('Dry run'),
            WhatsAppConfig::dryRun()
                ? __('ON — messages are logged but not actually sent')
                : __('Off — real messages are being sent')
        );

        $paused = WhatsAppConfig::pausedUntil();
        if ($paused && $paused->isFuture()) {
            $this->add(self::FAIL, __('Pause flag'), __('Paused until') . ' ' . $paused->toDateTimeString());
        }
    }

    private function checkToken(): void
    {
        $appId     = (string) config('services.meta.app_id');
        $appSecret = (string) config('services.meta.app_secret');

        if ($appId === '' || $appSecret === '') {
            $this->add(self::WARN, __('Token'), 'META_APP_ID / META_APP_SECRET ' . __('missing — cannot verify the token'));

            return;
        }

        $res = (new MetaCloudClient())->debugToken($appId, $appSecret);

        if (!($res['success'] ?? false)) {
            $this->add(self::FAIL, __('Token'), ($res['error'] ?? __('verification failed')));

            return;
        }

        $d = $res['data'];

        if (!($d['is_valid'] ?? false)) {
            $this->add(self::FAIL, __('Token'), __('The token is NOT valid'));

            return;
        }

        $hasSend = in_array('whatsapp_business_messaging', $d['scopes'] ?? [], true);
        $this->add(
            $hasSend ? self::PASS : self::FAIL,
            __('Token scopes'),
            $hasSend ? 'whatsapp_business_messaging ✓' : 'whatsapp_business_messaging ' . __('MISSING')
        );

        // A USER token carries a data-access clock even when expires_at is 0.
        // A System User token has neither.
        $dae = (int) ($d['data_access_expires_at'] ?? 0);
        if ($dae > 0) {
            $days = (int) floor(($dae - time()) / 86400);
            $this->add(
                $days < 30 ? self::FAIL : ($days < 60 ? self::WARN : self::PASS),
                __('Token lifetime'),
                sprintf(
                    '%s · %s %d %s (%s)%s',
                    $d['type'] ?? '?',
                    __('expires in'),
                    $days,
                    __('days'),
                    date('Y-m-d', $dae),
                    ($d['type'] ?? '') === 'USER' ? ' — ' . __('switch to a System User token') : ''
                )
            );
        } else {
            $this->add(self::PASS, __('Token lifetime'), __('No expiry (System User token)'));
        }

        $waba = WhatsAppConfig::wabaId();
        if ($waba !== '') {
            $granted = false;
            foreach ($d['granular_scopes'] ?? [] as $g) {
                if (($g['scope'] ?? '') === 'whatsapp_business_messaging'
                    && in_array($waba, $g['target_ids'] ?? [], true)) {
                    $granted = true;
                    break;
                }
            }
            $this->add(
                $granted ? self::PASS : self::WARN,
                __('Token grants access to the WABA'),
                $granted ? $waba . ' ✓' : $waba . ' ' . __('is not in the granular scopes')
            );
        }
    }

    private function checkPhoneNumber(): void
    {
        $res = (new MetaCloudClient())->get(WhatsAppConfig::phoneNumberId(), [
            'fields' => 'verified_name,display_phone_number,quality_rating,status,platform_type,throughput',
        ]);

        if (!($res['success'] ?? false)) {
            $this->add(self::FAIL, __('Sender number'), $res['error'] ?? __('lookup failed'));

            return;
        }

        $d  = $res['data'];
        $ok = ($d['status'] ?? '') === 'CONNECTED' && ($d['platform_type'] ?? '') === 'CLOUD_API';

        $this->add($ok ? self::PASS : self::FAIL, __('Sender number'), sprintf(
            '%s (%s) · %s · %s',
            $d['display_phone_number'] ?? '?',
            $d['verified_name'] ?? '?',
            $d['status'] ?? '?',
            $d['platform_type'] ?? '?'
        ));

        $q = $d['quality_rating'] ?? 'UNKNOWN';
        $this->add(
            $q === 'GREEN' ? self::PASS : self::WARN,
            __('Quality rating'),
            $q . ($q === 'GREEN' ? '' : ' — ' . __('reduce the volume'))
        );
    }

    private function checkHealth(): void
    {
        $res = (new MetaCloudClient())->get(WhatsAppConfig::phoneNumberId(), ['fields' => 'health_status']);

        if (!($res['success'] ?? false)) {
            $this->add(self::WARN, __('Send capability'), $res['error'] ?? __('lookup failed'));

            return;
        }

        $blocked = [];
        foreach ($res['data']['health_status']['entities'] ?? [] as $e) {
            // can_receive_call_sip entries concern SIP calling and are irrelevant here.
            if (($e['can_send_message'] ?? 'AVAILABLE') !== 'AVAILABLE') {
                $blocked[] = ($e['entity_type'] ?? '?') . '=' . $e['can_send_message'];
            }
        }

        $this->add(
            $blocked ? self::FAIL : self::PASS,
            __('Send capability'),
            $blocked ? implode(', ', $blocked) : __('AVAILABLE at every level')
        );
    }

    private function checkTemplate(): void
    {
        $waba = WhatsAppConfig::wabaId();
        if ($waba === '') {
            $this->add(self::WARN, __('Template'), __('WABA id not set — cannot verify the template'));

            return;
        }

        $name = WhatsAppConfig::template();
        $res  = (new MetaCloudClient())->get($waba . '/message_templates', ['name' => $name]);

        if (!($res['success'] ?? false)) {
            $this->add(self::FAIL, __('Template'), $res['error'] ?? __('lookup failed'));

            return;
        }

        $match = null;
        foreach ($res['data']['data'] ?? [] as $t) {
            if (($t['name'] ?? '') === $name && ($t['language'] ?? '') === WhatsAppConfig::language()) {
                $match = $t;
                break;
            }
        }

        if (!$match) {
            $this->add(self::FAIL, __('Template'), sprintf(
                '%s "%s" (%s)',
                __('No approved template named'),
                $name,
                WhatsAppConfig::language()
            ));

            return;
        }

        $status = $match['status'] ?? '?';
        $this->add(
            $status === 'APPROVED' ? self::PASS : self::FAIL,
            __('Template status'),
            $name . ' = ' . $status
        );

        // Counting placeholders here catches the whole 132000 class of bug at diagnose time
        // instead of at send time.
        $body = '';
        $hasUrlButton = false;
        foreach ($match['components'] ?? [] as $c) {
            if (($c['type'] ?? '') === 'BODY') {
                $body = $c['text'] ?? '';
            }
            if (($c['type'] ?? '') === 'BUTTONS') {
                foreach ($c['buttons'] ?? [] as $b) {
                    if (($b['type'] ?? '') === 'URL') {
                        $hasUrlButton = true;
                    }
                }
            }
        }

        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);
        $count = count(array_unique($m[1] ?? []));

        $this->add(
            $count === self::EXPECTED_PARAMS ? self::PASS : self::FAIL,
            __('Template parameters'),
            sprintf('%s %d · %s %d', __('template declares'), $count, __('code sends'), self::EXPECTED_PARAMS)
        );

        $this->add(
            $hasUrlButton ? self::PASS : self::WARN,
            __('Template button'),
            $hasUrlButton ? __('dynamic URL button present') : __('no URL button — the link will be dropped')
        );
    }

    private function checkWabaOwnsNumber(): void
    {
        $waba = WhatsAppConfig::wabaId();
        if ($waba === '') {
            return;
        }

        $res = (new MetaCloudClient())->get($waba . '/phone_numbers', ['fields' => 'id,display_phone_number']);
        if (!($res['success'] ?? false)) {
            $this->add(self::WARN, __('WABA owns the number'), $res['error'] ?? __('lookup failed'));

            return;
        }

        $ok = in_array(WhatsAppConfig::phoneNumberId(), array_column($res['data']['data'] ?? [], 'id'), true);

        $this->add(
            $ok ? self::PASS : self::FAIL,
            __('WABA owns the number'),
            $ok ? __('yes') : __('the configured number does NOT belong to this WABA')
        );
    }

    private function checkCron(): void
    {
        $crontab = @shell_exec('crontab -l 2>/dev/null') ?: '';
        $has = str_contains($crontab, 'whatsapp:dispatch');

        $this->add(
            $has ? self::PASS : self::WARN,
            __('Cron (dispatch)'),
            $has ? __('installed for the current user') : __('not found for the current user — check `crontab -u erpmy5243 -l`')
        );

        $stuck = DB::table('whatsapp_messages')
            ->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(15))
            ->count();

        if ($stuck > 0) {
            $this->add(self::FAIL, __('Stuck rows'), $stuck . ' ' . __("rows have been 'sending' for over 15 minutes"));
        }
    }

    private function checkOutbox(): void
    {
        $counts = DB::table('whatsapp_messages')
            ->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status')->toArray();

        $this->add(self::INFO, __('Outbox'), $counts
            ? implode(' · ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($counts), $counts))
            : __('empty'));

        $oldest = DB::table('whatsapp_messages')->where('status', 'pending')->min('created_at');
        if ($oldest) {
            $mins = (int) round((time() - strtotime($oldest)) / 60);
            $this->add($mins > 15 ? self::WARN : self::PASS, __('Oldest pending'), $mins . ' ' . __('min'));
        }

        $recentFail = DB::table('whatsapp_messages')
            ->where('status', 'failed')->where('updated_at', '>', now()->subDay())
            ->selectRaw('error_code, COUNT(*) c')->groupBy('error_code')->pluck('c', 'error_code')->toArray();

        if ($recentFail) {
            $this->add(self::WARN, __('Failures (24h)'), implode(' · ', array_map(
                fn ($k, $v) => __('code') . " {$k}: {$v}",
                array_keys($recentFail),
                $recentFail
            )));
        }
    }

    private function checkRecipients(): void
    {
        $total   = User::where('is_active', 1)->count();
        $withNum = User::where('is_active', 1)->whereNotNull('whatsapp')->where('whatsapp', '!=', '')->count();
        $invalid = User::where('whatsapp_status', 'invalid')->count();

        $this->add(
            $withNum > 0 ? self::PASS : self::FAIL,
            __('Recipients'),
            sprintf('%d / %d %s', $withNum, $total, __('active users have a number'))
        );

        if ($invalid > 0) {
            $this->add(self::WARN, __('Invalid numbers'), $invalid . ' ' . __('flagged invalid (Meta error 131026)'));
        }
    }

    private function add(string $level, string $name, string $detail): void
    {
        $this->results[] = compact('level', 'name', 'detail');
    }
}
