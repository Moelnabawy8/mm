<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\MetaCloudClient;
use App\Support\WhatsApp\WhatsAppConfig;
use App\Support\WhatsApp\WhatsAppGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Drains the whatsapp_messages outbox. Runs from cron every minute under flock, as the
 * site user erpmy5243 — never root, or artisan creates root-owned files in storage/ and
 * the next web request under lsphp 500s the whole site.
 *
 * Deliberately not a Laravel queue worker: the `jobs` table name is taken by the HRM
 * recruitment module, and a cron process is easier to reason about and harder to silently
 * lose than a daemon on a box with no supervisor.
 */
class WhatsAppDispatch extends Command
{
    protected $signature = 'whatsapp:dispatch
                            {--max-runtime=50 : seconds before the run bows out for the next cron tick}
                            {--limit=25 : rows claimed per batch}';

    protected $description = 'Send pending WhatsApp task notifications from the outbox';

    /** Retry schedule in minutes, indexed by attempt number. */
    private const BACKOFF = [1, 5, 15, 60];

    public function handle(): int
    {
        WhatsAppGate::allowCli();
        WhatsAppConfig::flush();

        // Cheap early exit: this runs 1440 times a day and is almost always a no-op.
        $waiting = DB::table('whatsapp_messages')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->limit(1)
            ->count();

        if ($waiting === 0) {
            return self::SUCCESS;
        }

        if (!WhatsAppConfig::enabled()) {
            $this->warn('WhatsApp notifications are disabled; leaving the outbox untouched.');

            return self::SUCCESS;
        }

        $paused = WhatsAppConfig::pausedUntil();
        if ($paused && $paused->isFuture()) {
            $this->warn('Paused until ' . $paused->toDateTimeString() . ' — skipping.');

            return self::SUCCESS;
        }

        $dryRun   = WhatsAppConfig::dryRun();
        $deadline = now()->addSeconds(max(5, (int) $this->option('max-runtime')));
        $limit    = max(1, (int) $this->option('limit'));
        $client   = new MetaCloudClient();

        $sent = $failed = 0;

        while (now()->lt($deadline)) {
            $claim = (string) Str::uuid();

            // A bare UPDATE ... LIMIT is atomic in MySQL, so this is race-safe on its own —
            // no transaction and no SKIP LOCKED needed even if the flock guard ever fails.
            $claimed = DB::table('whatsapp_messages')
                ->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('id')
                ->limit($limit)
                ->update([
                    'status'      => 'sending',
                    'claim_token' => $claim,
                    'attempts'    => DB::raw('attempts + 1'),
                    'updated_at'  => now(),
                ]);

            if ($claimed === 0) {
                break;
            }

            foreach (WhatsAppMessage::where('claim_token', $claim)->orderBy('id')->get() as $msg) {
                if ($this->sentToday() >= WhatsAppConfig::dailyCap()) {
                    $msg->update([
                        'status'          => 'pending',
                        'claim_token'     => null,
                        'attempts'        => max(0, $msg->attempts - 1),
                        'next_attempt_at' => now()->addHour(),
                    ]);
                    $this->warn('Daily cap reached (' . WhatsAppConfig::dailyCap() . '); deferring the rest.');

                    return self::SUCCESS;
                }

                $result = $this->deliver($client, $msg, $dryRun);

                if ($result === 'sent') {
                    $sent++;
                } elseif ($result === 'failed') {
                    $failed++;
                } elseif ($result === 'paused') {
                    $this->error('Global pause triggered; stopping this run.');
                    $this->line("sent={$sent} failed={$failed}");

                    return self::SUCCESS;
                }
            }
        }

        $this->line(($dryRun ? '[DRY RUN] ' : '') . "sent={$sent} failed={$failed}");

        return self::SUCCESS;
    }

    /** @return string one of sent|failed|retry|paused */
    private function deliver(MetaCloudClient $client, WhatsAppMessage $msg, bool $dryRun): string
    {
        $payload   = $msg->payload ?? [];
        $params    = $payload['params'] ?? [];
        $urlSuffix = $payload['url_suffix'] ?? null;
        $to        = ltrim((string) $msg->phone, '+');

        if ($to === '' || empty($params)) {
            $msg->update([
                'status'        => 'failed',
                'claim_token'   => null,
                'error_code'    => 0,
                'error_message' => 'Missing phone or template parameters on the outbox row.',
            ]);

            return 'failed';
        }

        if ($dryRun) {
            $msg->update([
                'status'              => 'sent',
                'claim_token'         => null,
                'provider_message_id' => 'DRYRUN',
                'sent_at'             => now(),
                'error_code'          => null,
                'error_message'       => null,
            ]);
            $this->countSend();

            return 'sent';
        }

        $res = $client->sendTemplate(
            $to,
            $msg->template_name,
            $msg->language,
            $params,
            $urlSuffix
        );

        if ($res['success']) {
            $msg->update([
                'status'              => 'sent',
                'claim_token'         => null,
                'provider_message_id' => $res['message_id'],
                'sent_at'             => now(),
                'error_code'          => null,
                'error_message'       => null,
                'payload'             => array_merge($payload, ['sent' => $res['payload']]),
            ]);

            $this->markNumberValid($msg);
            $this->countSend();

            return 'sent';
        }

        $class = $res['class'] ?? MetaCloudClient::CLASS_RETRYABLE;
        $code  = (int) ($res['code'] ?? 0);

        if ($class === MetaCloudClient::CLASS_GLOBAL_PAUSE) {
            // Give the row its attempt back — the failure was ours, not the recipient's.
            $msg->update([
                'status'          => 'pending',
                'claim_token'     => null,
                'attempts'        => max(0, $msg->attempts - 1),
                'next_attempt_at' => now()->addMinutes(30),
                'error_code'      => $code,
                'error_message'   => $res['error'] ?? null,
            ]);

            WhatsAppConfig::put('whatsapp_paused_until', now()->addMinutes(30)->toDateTimeString());

            Log::critical('[whatsapp] global pause', [
                'code'  => $code,
                'error' => $res['error'] ?? null,
            ]);

            return 'paused';
        }

        if ($class === MetaCloudClient::CLASS_PERMANENT) {
            $msg->update([
                'status'        => 'failed',
                'claim_token'   => null,
                'error_code'    => $code,
                'error_message' => $res['error'] ?? null,
                'payload'       => array_merge($payload, ['sent' => $res['payload'] ?? []]),
            ]);

            if ($code === 131026) {
                // The only real signal that a number is not on WhatsApp.
                User::where('id', $msg->user_id)->update(['whatsapp_status' => 'invalid']);
            }

            if ($code === 132007) {
                Log::error('[whatsapp] template format violation — sanitizer bug', [
                    'message_id' => $msg->id,
                    'params'     => $params,
                ]);
            }

            return 'failed';
        }

        // Retryable. `attempts` was already incremented by the claiming UPDATE, so it is 1
        // on the first try — attempt N waits BACKOFF[N-1], and the row only fails once the
        // whole schedule has been used.
        $attempt = (int) $msg->attempts;
        if ($attempt > count(self::BACKOFF)) {
            $msg->update([
                'status'        => 'failed',
                'claim_token'   => null,
                'error_code'    => $code,
                'error_message' => $res['error'] ?? null,
            ]);

            return 'failed';
        }

        $msg->update([
            'status'          => 'pending',
            'claim_token'     => null,
            'next_attempt_at' => now()->addMinutes(self::BACKOFF[$attempt - 1] ?? 60),
            'error_code'      => $code,
            'error_message'   => $res['error'] ?? null,
        ]);

        return 'retry';
    }

    private function markNumberValid(WhatsAppMessage $msg): void
    {
        if (!$msg->user_id) {
            return;
        }

        User::where('id', $msg->user_id)
            ->where('whatsapp_status', '!=', 'valid')
            ->update(['whatsapp_status' => 'valid']);
    }

    private ?int $sentTodayCache = null;

    /**
     * How many messages have actually gone out today. Read once from the database, then
     * tracked in memory — countSend() is called only on a real send, so failures and
     * retries never eat into the cap.
     */
    private function sentToday(): int
    {
        if ($this->sentTodayCache === null) {
            $this->sentTodayCache = (int) DB::table('whatsapp_messages')
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->whereDate('sent_at', now()->toDateString())
                ->count();
        }

        return $this->sentTodayCache;
    }

    private function countSend(): void
    {
        $this->sentTodayCache = $this->sentToday() + 1;
    }
}
