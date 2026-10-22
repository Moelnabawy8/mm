<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsApp\WhatsAppConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API client. Style mirrors App\Services\TelegramService: Http facade,
 * array{success: bool, ...} returns, no exceptions leaking to callers.
 *
 * Always sends `type: template`, never `type: text`. Free-form messages are only allowed
 * within 24 hours of the recipient's last inbound message, and employees will never message
 * the business number — a free-form attempt would just return 131047 every time.
 */
class MetaCloudClient
{
    public const CLASS_PERMANENT    = 'permanent';
    public const CLASS_RETRYABLE    = 'retryable';
    public const CLASS_GLOBAL_PAUSE = 'global_pause';

    /** Fail immediately; retrying cannot help and would just burn attempts. */
    private const PERMANENT = [
        131026, // message undeliverable / recipient is not a WhatsApp user
        132000, // template parameter count mismatch
        132001, // template does not exist in this language / not approved
        132005, // hydrated text too long
        132007, // template format character policy violated (sanitizer bug)
        132012, // parameter format mismatch
        132015, // template paused for quality
        132016, // template disabled
        131047, // re-engagement required (means we sent the wrong message type)
        131049, // per-user marketing limit (should never hit UTILITY)
        133010, // phone number not registered
        100,    // invalid parameter
        131008, // required parameter missing
        131009, // parameter value invalid
    ];

    /** Transient; back off and try again. */
    private const RETRYABLE = [
        80007,  // rate limit
        130429, // throughput limit
        131056, // pair rate limit (same business/recipient pair)
        131000, // generic internal error
        133016, // account in restore process
        1,      // unknown API error
        2,      // temporary API service issue
        4,      // application request limit reached
    ];

    /** Stop the entire run; every other pending row keeps its attempt count. */
    private const GLOBAL_PAUSE = [
        190, // access token expired / invalidated
        368, // temporarily blocked for policy violations
        10,  // permission denied
        200, // permission error
    ];

    private string $token;
    private string $phoneNumberId;
    private string $version;

    public function __construct(?string $token = null, ?string $phoneNumberId = null, ?string $version = null)
    {
        $this->token         = $token ?? WhatsAppConfig::token();
        $this->phoneNumberId = $phoneNumberId ?? WhatsAppConfig::phoneNumberId();
        $this->version       = $version ?? WhatsAppConfig::apiVersion();
    }

    /**
     * @param  string    $to         digits only, no '+'
     * @param  string[]  $params     body parameters, already sanitized
     * @param  ?string   $urlSuffix  dynamic suffix for the template's URL button
     * @return array{success: bool, message_id?: string, code?: int, class?: string, error?: string, payload: array}
     */
    public function sendTemplate(
        string $to,
        string $template,
        string $lang,
        array $params,
        ?string $urlSuffix = null
    ): array {
        $components = [[
            'type'       => 'body',
            'parameters' => array_map(
                fn ($p) => ['type' => 'text', 'text' => (string) $p],
                array_values($params)
            ),
        ]];

        if ($urlSuffix !== null && $urlSuffix !== '') {
            $components[] = [
                'type'       => 'button',
                'sub_type'   => 'url',
                'index'      => '0',
                'parameters' => [['type' => 'text', 'text' => $urlSuffix]],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'       => $template,
                'language'   => ['code' => $lang],
                'components' => $components,
            ],
        ];

        if ($this->token === '' || $this->phoneNumberId === '') {
            return [
                'success' => false,
                'class'   => self::CLASS_GLOBAL_PAUSE,
                'code'    => 0,
                'error'   => 'WhatsApp is not configured (missing token or phone number id).',
                'payload' => $payload,
            ];
        }

        $url = sprintf('%s/%s/%s/messages', WhatsAppConfig::GRAPH_BASE, $this->version, $this->phoneNumberId);

        try {
            $res = Http::withToken($this->token)
                ->timeout(20)
                ->connectTimeout(8)
                ->acceptJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('[whatsapp] transport failure', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'class'   => self::CLASS_RETRYABLE,
                'code'    => 0,
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ];
        }

        if ($res->successful() && $res->json('messages.0.id')) {
            return [
                'success'    => true,
                'message_id' => (string) $res->json('messages.0.id'),
                'payload'    => $payload,
            ];
        }

        $code = (int) ($res->json('error.code') ?? 0);
        $msg  = $res->json('error.message')
            ?? $res->json('error.error_data.details')
            ?? ('HTTP ' . $res->status());

        Log::warning('[whatsapp] send failed', [
            'code'   => $code,
            'status' => $res->status(),
            'error'  => $msg,
            'to'     => $to,
        ]);

        return [
            'success' => false,
            'code'    => $code,
            'class'   => self::classify($code, $res->status()),
            'error'   => is_string($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE),
            'payload' => $payload,
        ];
    }

    public static function classify(int $code, int $httpStatus = 0): string
    {
        if (in_array($code, self::GLOBAL_PAUSE, true)) {
            return self::CLASS_GLOBAL_PAUSE;
        }
        if (in_array($code, self::PERMANENT, true)) {
            return self::CLASS_PERMANENT;
        }
        if (in_array($code, self::RETRYABLE, true)) {
            return self::CLASS_RETRYABLE;
        }
        if ($httpStatus >= 500 || $httpStatus === 429 || $httpStatus === 0) {
            return self::CLASS_RETRYABLE;
        }
        if ($httpStatus === 401 || $httpStatus === 403) {
            return self::CLASS_GLOBAL_PAUSE;
        }

        // Unknown 4xx: treat as permanent so we don't spend four attempts on a config bug.
        return self::CLASS_PERMANENT;
    }

    /* ---------------------------------------------------------------- diagnostics */

    /** GET /{version}/{node}?fields=... — shared by whatsapp:doctor. */
    public function get(string $node, array $query = []): array
    {
        $url = sprintf('%s/%s/%s', WhatsAppConfig::GRAPH_BASE, $this->version, ltrim($node, '/'));

        try {
            $res = Http::withToken($this->token)->timeout(20)->connectTimeout(8)
                ->acceptJson()->get($url, $query);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (!$res->successful()) {
            return [
                'success' => false,
                'code'    => (int) ($res->json('error.code') ?? 0),
                'error'   => $res->json('error.message') ?? ('HTTP ' . $res->status()),
            ];
        }

        return ['success' => true, 'data' => $res->json()];
    }

    /**
     * Create the task-notification template on a WABA.
     *
     * Business-initiated WhatsApp messages must use a pre-approved template, and a template
     * lives on ONE WhatsApp Business Account. Switching the sender to a number on a different
     * WABA therefore needs the template created there too, or every send fails with 132001.
     *
     * Category UTILITY is deliberate: it is cheaper than MARKETING and is not subject to the
     * per-user marketing cap (error 131049).
     */
    public function createTaskTemplate(string $wabaId, string $name, string $lang, string $appUrl): array
    {
        $body = "مرحباً {{1}} 👋\n\n🔔 {{2}}\n\n📌 المهمة: {{3}}\n📁 المشروع: {{4}}\n"
              . "📅 التاريخ: {{5}}\nℹ️ {{6}}\n\nلعرض التفاصيل، افتح النظام من الزر أدناه.";

        $payload = [
            'name'       => $name,
            'language'   => $lang,
            'category'   => 'UTILITY',
            'components' => [
                [
                    'type'    => 'BODY',
                    'text'    => $body,
                    'example' => ['body_text' => [[
                        'أحمد', 'تم إسناد مهمة جديدة إليك', 'تجهيز عرض العميل',
                        'مشروع التطوير', '2026-08-01', 'الأولوية: عالية',
                    ]]],
                ],
                ['type' => 'FOOTER', 'text' => 'نظام My-Communication ERP'],
                [
                    'type'    => 'BUTTONS',
                    'buttons' => [[
                        'type'    => 'URL',
                        'text'    => 'فتح النظام',
                        'url'     => rtrim($appUrl, '/') . '/{{1}}',
                        'example' => [rtrim($appUrl, '/') . '/projects/12'],
                    ]],
                ],
            ],
        ];

        $url = sprintf('%s/%s/%s/message_templates', WhatsAppConfig::GRAPH_BASE, $this->version, $wabaId);

        try {
            $res = Http::withToken($this->token)->timeout(30)->connectTimeout(8)
                ->acceptJson()->post($url, $payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($res->successful() && $res->json('id')) {
            return [
                'success' => true,
                'id'      => $res->json('id'),
                'status'  => $res->json('status') ?? 'PENDING',
            ];
        }

        return [
            'success' => false,
            'code'    => (int) ($res->json('error.code') ?? 0),
            'error'   => $res->json('error.error_user_msg')
                ?? $res->json('error.message')
                ?? ('HTTP ' . $res->status()),
        ];
    }

    /** debug_token needs the app token, not the user token. */
    public function debugToken(string $appId, string $appSecret): array
    {
        $url = sprintf('%s/%s/debug_token', WhatsAppConfig::GRAPH_BASE, $this->version);

        try {
            $res = Http::timeout(20)->connectTimeout(8)->acceptJson()->get($url, [
                'input_token'  => $this->token,
                'access_token' => $appId . '|' . $appSecret,
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (!$res->successful()) {
            return ['success' => false, 'error' => $res->json('error.message') ?? ('HTTP ' . $res->status())];
        }

        return ['success' => true, 'data' => $res->json('data') ?? []];
    }
}
