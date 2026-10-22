<?php

namespace App\Http\Controllers;

use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use App\Support\Telegram\TelegramConfig;
use App\Support\Telegram\TelegramNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admin + self-service surface for Telegram task notifications.
 *
 * Two audiences:
 *  - every employee: the linking page (telegram/link) binds their own chat via a
 *    one-time token + t.me deep-link. No permission required — it only touches
 *    their own row. No webhook involved: telegram:dispatch picks the /start up
 *    via getUpdates within a minute.
 *  - company admin: settings, recipients overview, delivery log (permissions
 *    `manage telegram notification` / `manage telegram recipient` / `view telegram log`).
 *
 * Settings persist through the same raw INSERT ... ON DUPLICATE KEY UPDATE loop that
 * SystemController@saveTwilioSettings uses, so the `settings` table keeps one idiom.
 */
class TelegramController extends Controller
{
    /** Keys owned by the settings form. Anything else in the request is ignored. */
    private const TEXT_KEYS = [
        'telegram_bot_username',
        'telegram_daily_cap',
        'telegram_timezone',
    ];

    private const TOGGLE_KEYS = [
        'telegram_enabled',
        'telegram_dry_run',
        'telegram_notify_assign',
        'telegram_notify_stage',
        'telegram_notify_comment',
        'telegram_notify_due',
    ];

    /**
     * The company's own users PLUS the company account itself. The company owner
     * (id = creatorId) is created_by the super admin, so a plain created_by filter
     * hid them from their own recipients page even after they linked Telegram.
     */
    private static function companyUsers()
    {
        $cid = Auth::user()->creatorId();

        return User::where(function ($q) use ($cid) {
            $q->where('created_by', $cid)->orWhere('id', $cid);
        });
    }

    /** Link tokens live 15 minutes and are single-use. */
    private const LINK_TTL_MINUTES = 15;

    /* ------------------------------------------------------------------ settings */

    public function saveSettings(Request $request)
    {
        if (!Auth::user()->can('manage telegram notification')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $post = [];

        // Read the raw POST, not $request->input(): the global FilterRequest middleware strips
        // the substrings 'alert'/'javascript' from every value. A bot token is opaque and could
        // in principle contain them — same trap that once saved the WhatsApp template
        // `task_alert_ar` as `task__ar`. Nothing here is rendered unescaped.
        $rawToken = trim((string) ($_POST['telegram_bot_token'] ?? ''));
        if ($rawToken !== '') {
            $post['telegram_bot_token'] = $rawToken;
        }
        // An empty token field means "keep the stored one" — the form never echoes the
        // secret back, so submitting the page must not wipe it.

        foreach (self::TEXT_KEYS as $key) {
            if ($request->has($key)) {
                $post[$key] = trim((string) ($_POST[$key] ?? $request->input($key)));
            }
        }

        if (isset($post['telegram_bot_username'])) {
            $post['telegram_bot_username'] = ltrim($post['telegram_bot_username'], '@');
            if ($post['telegram_bot_username'] !== ''
                && !preg_match('/^[A-Za-z0-9_]{5,32}$/', $post['telegram_bot_username'])) {
                return redirect()->back()->with('error', __('Invalid bot username.'));
            }
        }

        foreach (self::TOGGLE_KEYS as $key) {
            $post[$key] = $request->has($key) ? '1' : '0';
        }

        $offsets = (array) $request->input('telegram_due_offsets', []);
        $post['telegram_due_offsets'] = implode(',', array_intersect($offsets, ['d0', 'd1'])) ?: 'd1';

        // Turning the feature back on should clear a stale pause left by a 401.
        if (($post['telegram_enabled'] ?? '0') === '1') {
            $post['telegram_paused_until'] = '';
        }

        $now = date('Y-m-d H:i:s');
        foreach ($post as $key => $value) {
            DB::insert(
                'INSERT INTO settings (`value`, `name`, `created_by`, `created_at`, `updated_at`)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = VALUES(`updated_at`)',
                [$value, $key, Auth::user()->creatorId(), $now, $now]
            );
        }

        TelegramConfig::flush();

        return redirect()->back()->with('success', __('Telegram settings updated successfully.'));
    }

    /**
     * "Test connection" button in the settings panel: getMe proves the token is valid
     * and returns the bot's name/username.
     */
    public function connection()
    {
        if (!Auth::user()->can('manage telegram notification')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $res = (new TelegramClient())->getMe();

        if (!($res['success'] ?? false)) {
            return response()->json([
                'connected' => false,
                'headline'  => __('Not connected — check the bot token.'),
                'error'     => $res['error'] ?? null,
            ]);
        }

        $bot = $res['bot'] ?? [];

        return response()->json([
            'connected' => true,
            'headline'  => __('Connected to Telegram successfully.'),
            'bot'       => [
                'name'     => $bot['first_name'] ?? '',
                'username' => $bot['username'] ?? '',
            ],
        ]);
    }

    /* ------------------------------------------------------------------- linking */

    /**
     * Self-service linking page: shows the t.me deep-link with a fresh one-time token
     * plus "tap START" instructions. Linking completes on the next telegram:dispatch
     * tick (within a minute) — no webhook, no polling from the browser.
     */
    public function link()
    {
        $user = Auth::user();
        $botUsername = TelegramConfig::botUsername();

        $token = DB::table('telegram_link_tokens')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        $deepLink = ($token && $botUsername !== '')
            ? 'https://t.me/' . $botUsername . '?start=' . $token->token
            : null;

        return view('telegram.link', [
            'linkUser'    => $user,
            'botUsername' => $botUsername,
            'deepLink'    => $deepLink,
            'expiresAt'   => $token->expires_at ?? null,
            'configured'  => TelegramConfig::configured() && $botUsername !== '',
        ]);
    }

    /** Mint a fresh token (invalidating older unused ones) for the current user. */
    public function regenerateLink()
    {
        $user = Auth::user();

        DB::table('telegram_link_tokens')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        DB::table('telegram_link_tokens')->insert([
            'token'      => Str::random(32),
            'user_id'    => $user->id,
            'expires_at' => now()->addMinutes(self::LINK_TTL_MINUTES),
            'created_by' => $user->creatorId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('telegram.link')
            ->with('success', __('A fresh link has been generated. It expires in 15 minutes.'));
    }

    /** Unlink the current user's own chat. */
    public function unlinkOwn()
    {
        $user = Auth::user();
        $user->telegram_chat_id = null;
        $user->telegram_status = 'unset';
        $user->save();

        return redirect()->route('telegram.link')
            ->with('success', __('Telegram account unlinked.'));
    }

    /* --------------------------------------------------------------- recipients */

    public function recipients(Request $request)
    {
        if (!Auth::user()->can('manage telegram recipient')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $query = self::companyUsers()
            ->where('type', '!=', 'client');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telegram_chat_id', 'like', "%{$search}%");
            });
        }

        if ($request->input('filter') === 'missing') {
            $query->where(function ($q) {
                $q->whereNull('telegram_chat_id')->orWhere('telegram_chat_id', '');
            });
        } elseif ($request->input('filter') === 'invalid') {
            $query->where('telegram_status', 'invalid');
        }

        $users = $query->orderBy('name')->paginate(50)->withQueryString();

        $stats = [
            'total'   => self::companyUsers()->where('type', '!=', 'client')->count(),
            'with'    => self::companyUsers()->where('type', '!=', 'client')
                ->whereNotNull('telegram_chat_id')->where('telegram_chat_id', '!=', '')->count(),
            'invalid' => self::companyUsers()->where('telegram_status', 'invalid')->count(),
        ];

        return view('telegram.recipients', compact('users', 'stats'));
    }

    /** Admin: detach a chat (e.g. employee left, or a wrong account was linked). */
    public function unlinkRecipient($id)
    {
        if (!Auth::user()->can('manage telegram recipient')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $user = self::companyUsers()->where('id', $id)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $user->telegram_chat_id = null;
        $user->telegram_status = 'unset';
        $user->save();

        return redirect()->back()->with('success', __('Telegram account unlinked.'));
    }

    /**
     * The only real way to find out whether a chat is reachable — Telegram has no
     * pre-send lookup, so a live send is the sole source of truth.
     */
    public function testSend(Request $request, $id)
    {
        if (!Auth::user()->can('manage telegram recipient')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $user = self::companyUsers()->where('id', $id)->first();
        if (!$user || empty($user->telegram_chat_id)) {
            return response()->json(['error' => __('This user has no linked Telegram account.')], 422);
        }

        if (!TelegramConfig::configured()) {
            return response()->json(['error' => __('Telegram is not configured yet.')], 422);
        }

        $messageId = app(TelegramNotifier::class)->test($user->id);

        if (!$messageId) {
            return response()->json(['error' => __('Could not queue the test message.')], 422);
        }

        return response()->json([
            'success' => true,
            'message' => TelegramConfig::dryRun()
                ? __('Queued in dry-run mode — no real message was sent.')
                : __('Queued. It will be delivered within a minute.'),
            'id' => $messageId,
        ]);
    }

    /* ---------------------------------------------------------------------- log */

    public function log(Request $request)
    {
        if (!Auth::user()->can('view telegram log')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $companyId = Auth::user()->creatorId();

        $query = TelegramMessage::forCompany($companyId)->with(['user', 'task', 'project']);

        foreach (['status' => 'status', 'event_type' => 'event', 'user_id' => 'user'] as $col => $input) {
            if ($v = $request->input($input)) {
                $query->where($col, $v);
            }
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $messages = $query->orderByDesc('id')->paginate(40)->withQueryString();

        $base = TelegramMessage::forCompany($companyId);
        $stats = [
            'total'      => (clone $base)->count(),
            'sent_24h'   => (clone $base)->whereIn('status', ['sent', 'delivered', 'read'])
                                ->where('created_at', '>', now()->subDay())->count(),
            'failed_24h' => (clone $base)->where('status', 'failed')
                                ->where('created_at', '>', now()->subDay())->count(),
            'pending'    => (clone $base)->whereIn('status', ['pending', 'sending'])->count(),
            'suppressed' => (int) (clone $base)->sum('duplicate_hits'),
        ];

        $stats['success_rate'] = ($stats['sent_24h'] + $stats['failed_24h']) > 0
            ? round($stats['sent_24h'] * 100 / ($stats['sent_24h'] + $stats['failed_24h']))
            : 100;

        $users = self::companyUsers()->orderBy('name')->pluck('name', 'id');

        return view('telegram.log', compact('messages', 'stats', 'users'));
    }

    public function resend($id)
    {
        if (!Auth::user()->can('view telegram log')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $msg = TelegramMessage::forCompany(Auth::user()->creatorId())->find($id);
        if (!$msg) {
            return redirect()->back()->with('error', __('Message not found.'));
        }

        $msg->update([
            'status'          => 'pending',
            'attempts'        => 0,
            'claim_token'     => null,
            'next_attempt_at' => now(),
            'error_code'      => null,
            'error_message'   => null,
        ]);

        return redirect()->back()->with('success', __('Queued for resending.'));
    }
}
