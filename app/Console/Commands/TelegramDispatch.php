<?php

namespace App\Console\Commands;

use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use App\Support\Telegram\TelegramConfig;
use App\Support\Telegram\TelegramGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Drains the telegram_messages outbox. Runs from cron every minute under flock, as the
 * site user erpmy5243 — never root, or artisan creates root-owned files in storage/ and
 * the next web request under lsphp 500s the whole site.
 *
 * Each run does two things, in order:
 *   1. Long-poll getUpdates (with a stored offset, so nothing is processed twice) and
 *      consume /start deep-links to bind an employee's chat_id. Telegram holds updates
 *      until they are acknowledged, so nothing is lost between ticks. NO webhook, NO
 *      public endpoint, NO csrf exception — this deliberately avoids the whole class
 *      of CSRF/gateway trouble the WhatsApp webhook had on this server.
 *   2. Send every due outbox row, with the same claim/backoff/cap discipline as
 *      whatsapp:dispatch.
 *
 * Deliberately not a Laravel queue worker: the `jobs` table name is taken by the HRM
 * recruitment module, and a cron process is easier to reason about and harder to silently
 * lose than a daemon on a box with no supervisor.
 */
class TelegramDispatch extends Command
{
    protected $signature = 'telegram:dispatch
                            {--max-runtime=50 : seconds before the run bows out for the next cron tick}
                            {--limit=25 : rows claimed per batch}';

    protected $description = 'Link Telegram chats via getUpdates and send pending task notifications';

    /** Retry schedule in minutes, indexed by attempt number. */
    private const BACKOFF = [1, 5, 15, 60];

    public function handle(): int
    {
        TelegramGate::allowCli();
        TelegramConfig::flush();

        $client = new TelegramClient();

        $this->consumeUpdates($client);

        // Cheap early exit: this runs 1440 times a day and is almost always a no-op.
        $waiting = DB::table('telegram_messages')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->limit(1)
            ->count();

        if ($waiting === 0) {
            return self::SUCCESS;
        }

        if (!TelegramConfig::enabled()) {
            $this->warn('Telegram notifications are disabled; leaving the outbox untouched.');

            return self::SUCCESS;
        }

        $paused = TelegramConfig::pausedUntil();
        if ($paused && $paused->isFuture()) {
            $this->warn('Paused until ' . $paused->toDateTimeString() . ' — skipping.');

            return self::SUCCESS;
        }

        $dryRun   = TelegramConfig::dryRun();
        $deadline = now()->addSeconds(max(5, (int) $this->option('max-runtime')));
        $limit    = max(1, (int) $this->option('limit'));

        $sent = $failed = 0;

        while (now()->lt($deadline)) {
            $claim = (string) Str::uuid();

            // A bare UPDATE ... LIMIT is atomic in MySQL, so this is race-safe on its own —
            // no transaction and no SKIP LOCKED needed even if the flock guard ever fails.
            $claimed = DB::table('telegram_messages')
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

            foreach (TelegramMessage::where('claim_token', $claim)->orderBy('id')->get() as $msg) {
                if ($this->sentToday() >= TelegramConfig::dailyCap()) {
                    $msg->update([
                        'status'          => 'pending',
                        'claim_token'     => null,
                        'attempts'        => max(0, $msg->attempts - 1),
                        'next_attempt_at' => now()->addHour(),
                    ]);
                    $this->warn('Daily cap reached (' . TelegramConfig::dailyCap() . '); deferring the rest.');

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

    /* --------------------------------------------------------------- inbound */

    /**
     * Pull inbound updates and consume /start deep-links.
     *
     * Always runs — even when notifications are disabled or the outbox is empty —
     * because linking is what makes future sends possible, and Telegram only holds
     * unacknowledged updates for a limited time. Costs one cheap HTTPS call per tick.
     */
    private function consumeUpdates(TelegramClient $client): void
    {
        if (!TelegramConfig::configured()) {
            return;
        }

        $offset = TelegramConfig::updateOffset();
        // offset 0 on first ever run would replay the whole backlog; start from "now".
        $res = $client->getUpdates($offset > 0 ? $offset : 0, 10);

        if (!($res['success'] ?? false)) {
            Log::warning('[telegram] getUpdates failed; linking deferred', [
                'error' => $res['error'] ?? null,
            ]);

            return;
        }

        $updates = $res['updates'] ?? [];

        // Nothing new → leave the offset alone. Bumping it on an empty poll crept it forward
        // by 1 every minute; update_ids are sequential, so after an idle hour the next real
        // /start had an id BELOW the offset and Telegram never returned it — the link was
        // silently lost.
        if (!$updates) {
            return;
        }

        $maxId = 0;
        foreach ($updates as $update) {
            $maxId = max($maxId, (int) ($update['update_id'] ?? 0));

            // One bad update must not block the ones after it, nor stop the offset advancing
            // (otherwise it would be replayed — and answered — every minute).
            try {
                $this->handleUpdate($client, $update);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Advance past everything we have seen, even the ones we ignored.
        if ($maxId > 0) {
            TelegramConfig::put('telegram_update_offset', (string) ($maxId + 1));
        }
    }

    private function handleUpdate(TelegramClient $client, array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $chatId = (string) ($message['chat']['id'] ?? '');

        if ($chatId === '' || !str_starts_with($text, '/start')) {
            return;
        }

        $token = trim(substr($text, strlen('/start')));

        if ($token === '') {
            // Tapped START with no payload (found the bot by search). Tell them how
            // to link instead of silently ignoring them.
            $client->sendMessage($chatId, 'مرحباً 👋' . "\n\n" . 'لربط حسابك بنظام ERP، افتح صفحة ربط تليجرام في النظام واضغط الرابط من هناك.');

            return;
        }

        $row = DB::table('telegram_link_tokens')
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            $client->sendMessage($chatId, 'الرابط غير صالح أو منتهي الصلاحية. اطلب رابطاً جديداً من صفحة ربط تليجرام في النظام.');

            return;
        }

        User::where('id', $row->user_id)->update([
            'telegram_chat_id' => $chatId,
            'telegram_status'  => 'unset',
        ]);

        DB::table('telegram_link_tokens')->where('id', $row->id)->update([
            'used_at'    => now(),
            'chat_id'    => $chatId,
            'updated_at' => now(),
        ]);

        Log::info('[telegram] chat linked', ['user_id' => $row->user_id, 'chat_id' => $chatId]);

        $client->sendMessage(
            $chatId,
            'تم ربط حسابك بنظام ERP بنجاح ✅' . "\n\n" . 'ستصلك إشعارات المهام هنا.'
        );
    }

    /* --------------------------------------------------------------- outbound */

    /** @return string one of sent|failed|retry|paused */
    private function deliver(TelegramClient $client, TelegramMessage $msg, bool $dryRun): string
    {
        $to = (string) $msg->chat_id;

        if ($to === '' || ($msg->body_html ?? '') === '') {
            $msg->update([
                'status'        => 'failed',
                'claim_token'   => null,
                'error_code'    => 0,
                'error_message' => 'Missing chat id or message body on the outbox row.',
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

        $res = $client->sendMessage($to, (string) $msg->body_html);

        if ($res['success']) {
            $msg->update([
                'status'              => 'sent',
                'claim_token'         => null,
                'provider_message_id' => (string) ($res['message_id'] ?? ''),
                'sent_at'             => now(),
                'error_code'          => null,
                'error_message'       => null,
            ]);

            $this->markChatValid($msg);
            $this->countSend();

            return 'sent';
        }

        $class = $res['class'] ?? TelegramClient::CLASS_RETRYABLE;
        $code  = (int) ($res['code'] ?? 0);

        if ($class === TelegramClient::CLASS_GLOBAL_PAUSE) {
            // Give the row its attempt back — the failure was ours, not the recipient's.
            $msg->update([
                'status'          => 'pending',
                'claim_token'     => null,
                'attempts'        => max(0, $msg->attempts - 1),
                'next_attempt_at' => now()->addMinutes(30),
                'error_code'      => $code,
                'error_message'   => $res['error'] ?? null,
            ]);

            TelegramConfig::put('telegram_paused_until', now()->addMinutes(30)->toDateTimeString());

            Log::critical('[telegram] global pause', [
                'code'  => $code,
                'error' => $res['error'] ?? null,
            ]);

            return 'paused';
        }

        if ($class === TelegramClient::CLASS_PERMANENT) {
            $msg->update([
                'status'        => 'failed',
                'claim_token'   => null,
                'error_code'    => $code,
                'error_message' => $res['error'] ?? null,
            ]);

            if ($msg->user_id && TelegramClient::isDeadChat($code, (string) ($res['error'] ?? ''))) {
                // The only real signal that a chat is gone (blocked/deleted).
                User::where('id', $msg->user_id)->update(['telegram_status' => 'invalid']);
            }

            return 'failed';
        }

        // Retryable. `attempts` was already incremented by the claiming UPDATE, so it is 1
        // on the first try — attempt N waits BACKOFF[N-1], and the row only fails once the
        // whole schedule has been used. A 429 retry_after longer than the backoff wins.
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

        $waitMinutes = self::BACKOFF[$attempt - 1] ?? 60;
        $retryAfter = (int) ($res['retry_after'] ?? 0);
        if ($retryAfter > 0) {
            $waitMinutes = max($waitMinutes, (int) ceil($retryAfter / 60));
        }

        $msg->update([
            'status'          => 'pending',
            'claim_token'     => null,
            'next_attempt_at' => now()->addMinutes($waitMinutes),
            'error_code'      => $code,
            'error_message'   => $res['error'] ?? null,
        ]);

        return 'retry';
    }

    private function markChatValid(TelegramMessage $msg): void
    {
        if (!$msg->user_id) {
            return;
        }

        User::where('id', $msg->user_id)
            ->where('telegram_status', '!=', 'valid')
            ->update(['telegram_status' => 'valid']);
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
            $this->sentTodayCache = (int) DB::table('telegram_messages')
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
