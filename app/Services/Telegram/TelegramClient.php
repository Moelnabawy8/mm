<?php

namespace App\Services\Telegram;

use App\Support\Telegram\TelegramConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API client. Style mirrors App\Services\WhatsApp\MetaCloudClient:
 * Http facade, array{success: bool, ...} returns, no exceptions leaking to callers.
 *
 * Unlike WhatsApp there are no templates: plain HTML text via sendMessage.
 * Unlike WhatsApp there are no delivery receipts: the Bot API reports success on
 * send, and getUpdates only carries INBOUND messages (used here for /start
 * linking). delivered_at/read_at therefore stay NULL on telegram_messages.
 */
class TelegramClient
{
    public const CLASS_PERMANENT    = 'permanent';
    public const CLASS_RETRYABLE    = 'retryable';
    public const CLASS_GLOBAL_PAUSE = 'global_pause';

    /**
     * Bot API descriptions that mean "this chat will never receive anything".
     * Matched case-insensitively as substrings of the `description` field.
     */
    private const DEAD_CHAT = [
        'chat not found',
        'bot was blocked by the user',
        'user is deactivated',
        'bot was kicked',
        'peer_id_invalid',
        'group chat was deleted',
        'chat_id is empty',
    ];

    private string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? TelegramConfig::botToken();
    }

    private function url(string $method): string
    {
        return sprintf('%s/bot%s/%s', TelegramConfig::API_BASE, $this->token, $method);
    }

    /**
     * @return array{success: bool, message_id?: int, code?: int, class?: string, error?: string, retry_after?: int}
     */
    public function sendMessage(string $chatId, string $html): array
    {
        if ($this->token === '') {
            return [
                'success' => false,
                'class'   => self::CLASS_GLOBAL_PAUSE,
                'code'    => 0,
                'error'   => 'Telegram is not configured (missing bot token).',
            ];
        }

        if ($chatId === '') {
            return [
                'success' => false,
                'class'   => self::CLASS_PERMANENT,
                'code'    => 0,
                'error'   => 'Missing chat id on the outbox row.',
            ];
        }

        try {
            $res = Http::timeout(20)
                ->connectTimeout(8)
                ->acceptJson()
                ->post($this->url('sendMessage'), [
                    'chat_id'    => $chatId,
                    'text'       => $html,
                    'parse_mode' => 'HTML',
                ]);
        } catch (\Throwable $e) {
            Log::warning('[telegram] transport failure', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'class'   => self::CLASS_RETRYABLE,
                'code'    => 0,
                'error'   => $e->getMessage(),
            ];
        }

        $body = $res->json() ?? [];

        if ($res->successful() && ($body['ok'] ?? false) && isset($body['result']['message_id'])) {
            return [
                'success'    => true,
                'message_id' => (int) $body['result']['message_id'],
            ];
        }

        $code = (int) ($body['error_code'] ?? $res->status());
        $desc = (string) ($body['description'] ?? ('HTTP ' . $res->status()));

        Log::warning('[telegram] send failed', [
            'code'    => $code,
            'status'  => $res->status(),
            'error'   => $desc,
            'chat_id' => $chatId,
        ]);

        return [
            'success'     => false,
            'code'        => $code,
            'class'       => self::classify($code, $desc, $res->status()),
            'error'       => $desc,
            'retry_after' => (int) ($body['parameters']['retry_after'] ?? 0),
        ];
    }

    /**
     * Long-poll inbound updates (only `message` updates: /start taps and replies).
     *
     * @return array{success: bool, updates?: array, error?: string}
     */
    public function getUpdates(int $offset, int $timeout = 10): array
    {
        if ($this->token === '') {
            return ['success' => false, 'error' => 'Telegram is not configured (missing bot token).'];
        }

        try {
            // Long poll on Telegram's side, but bounded: the cron tick must finish fast.
            $res = Http::timeout($timeout + 15)
                ->connectTimeout(8)
                ->acceptJson()
                ->get($this->url('getUpdates'), [
                    'offset'          => $offset,
                    'timeout'         => $timeout,
                    'allowed_updates' => json_encode(['message']),
                ]);
        } catch (\Throwable $e) {
            Log::warning('[telegram] getUpdates transport failure', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        $body = $res->json() ?? [];

        if ($res->successful() && ($body['ok'] ?? false)) {
            return ['success' => true, 'updates' => $body['result'] ?? []];
        }

        $desc = (string) ($body['description'] ?? ('HTTP ' . $res->status()));
        Log::warning('[telegram] getUpdates failed', ['error' => $desc]);

        return ['success' => false, 'error' => $desc];
    }

    /** getMe doubles as the "test connection" check: proves the token is valid. */
    public function getMe(): array
    {
        if ($this->token === '') {
            return ['success' => false, 'error' => 'Telegram is not configured (missing bot token).'];
        }

        try {
            $res = Http::timeout(20)->connectTimeout(8)->acceptJson()
                ->get($this->url('getMe'));
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $body = $res->json() ?? [];

        if ($res->successful() && ($body['ok'] ?? false)) {
            return ['success' => true, 'bot' => $body['result'] ?? []];
        }

        return ['success' => false, 'error' => (string) ($body['description'] ?? ('HTTP ' . $res->status()))];
    }

    public static function classify(int $code, string $description = '', int $httpStatus = 0): string
    {
        // 401 Unauthorized: revoked/invalid token — every row would fail identically.
        if ($code === 401 || $httpStatus === 401) {
            return self::CLASS_GLOBAL_PAUSE;
        }

        // 429 is transient by definition; the backoff schedule absorbs it.
        if ($code === 429 || $httpStatus === 429) {
            return self::CLASS_RETRYABLE;
        }

        if ($httpStatus >= 500 || $httpStatus === 0) {
            return self::CLASS_RETRYABLE;
        }

        $lower = mb_strtolower($description);
        foreach (self::DEAD_CHAT as $needle) {
            if (str_contains($lower, $needle)) {
                return self::CLASS_PERMANENT;
            }
        }

        // Unknown 4xx: treat as permanent so we don't spend four attempts on a config bug.
        return self::CLASS_PERMANENT;
    }

    /** True when the failure means the chat is gone (blocked/deleted) — flag the user. */
    public static function isDeadChat(int $code, string $description = ''): bool
    {
        $lower = mb_strtolower($description);
        foreach (self::DEAD_CHAT as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return in_array($code, [400, 403], true) && $description !== '';
    }
}
