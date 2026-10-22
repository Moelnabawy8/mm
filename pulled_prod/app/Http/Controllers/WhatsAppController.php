<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\WhatsApp\PhoneNormalizer;
use App\Support\WhatsApp\TaskNotifier;
use App\Support\WhatsApp\WhatsAppConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Admin surface for the WhatsApp task notifications: credentials, recipient numbers and
 * the delivery log.
 *
 * Settings persist through the same raw INSERT ... ON DUPLICATE KEY UPDATE loop that
 * SystemController@saveTwilioSettings uses, so the `settings` table keeps one idiom.
 */
class WhatsAppController extends Controller
{
    /** Keys owned by the settings form. Anything else in the request is ignored. */
    private const TEXT_KEYS = [
        'whatsapp_token',
        'whatsapp_phone_number_id',
        'whatsapp_waba_id',
        'whatsapp_template_name',
        'whatsapp_language',
        'whatsapp_default_country',
        'whatsapp_daily_cap',
        'whatsapp_api_version',
        'whatsapp_timezone',
        'whatsapp_webhook_verify_token',
    ];

    private const TOGGLE_KEYS = [
        'whatsapp_enabled',
        'whatsapp_dry_run',
        'whatsapp_notify_assign',
        'whatsapp_notify_stage',
        'whatsapp_notify_comment',
        'whatsapp_notify_due',
    ];

    /* ------------------------------------------------------------------ settings */

    public function saveSettings(Request $request)
    {
        if (!Auth::user()->can('manage whatsapp notification')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $post = [];

        foreach (self::TEXT_KEYS as $key) {
            if ($request->has($key)) {
                $post[$key] = trim((string) $request->input($key));
            }
        }

        // An empty token field means "keep the stored one" — the form never echoes the
        // secret back, so submitting the page must not wipe it.
        if (isset($post['whatsapp_token']) && $post['whatsapp_token'] === '') {
            unset($post['whatsapp_token']);
        }

        foreach (self::TOGGLE_KEYS as $key) {
            $post[$key] = $request->has($key) ? '1' : '0';
        }

        $offsets = (array) $request->input('whatsapp_due_offsets', []);
        $post['whatsapp_due_offsets'] = implode(',', array_intersect($offsets, ['d0', 'd1'])) ?: 'd1';

        // Turning the feature back on should clear a stale pause left by error 190/368.
        if (($post['whatsapp_enabled'] ?? '0') === '1') {
            $post['whatsapp_paused_until'] = '';
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

        WhatsAppConfig::flush();

        return redirect()->back()->with('success', __('WhatsApp settings updated successfully.'));
    }

    /**
     * "Test connection" button in the settings panel.
     *
     * Runs the exact same checks as `php artisan whatsapp:doctor` through the shared
     * ConnectionDoctor service, so the screen and the CLI can never report different states.
     *
     * The cron check is skipped here: shell_exec under the web server would read the web
     * user's crontab, not erpmy5243's, and would always report a false negative.
     */
    public function connection()
    {
        if (!Auth::user()->can('manage whatsapp notification')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $doctor  = new \App\Services\WhatsApp\ConnectionDoctor(remoteOnly: true);
        $checks  = $doctor->run();
        $summary = $doctor->summary();

        return response()->json([
            'connected' => $summary['connected'],
            'failed'    => $summary['failed'],
            'warnings'  => $summary['warnings'],
            'checks'    => $checks,
            'headline'  => $summary['connected']
                ? __('Connected to Meta successfully.')
                : __('Not connected — review the failed checks below.'),
        ]);
    }

    /* ------------------------------------------------------------------- webhook */

    /**
     * Meta calls this with GET once, at setup time, to prove we control the URL.
     *
     * Public route, no auth — Meta is the caller, not a logged-in user. Must live outside
     * any 'auth' middleware group and be excluded from VerifyCsrfToken.
     *
     * NOTE: this WhatsApp Business Account's webhook subscription currently points at
     * maya-os.com. Adding this endpoint does nothing on its own — the callback URL and
     * verify token must also be updated in Meta's App Dashboard (WhatsApp > Configuration)
     * to https://erp.my-communication.uk/whatsapp/webhook with the token saved below,
     * or Meta will keep calling the old URL and this one will never fire.
     */
    public function webhookVerify(Request $request)
    {
        if ($request->query('hub_mode') === 'subscribe'
            && WhatsAppConfig::webhookVerifyToken() !== ''
            && hash_equals(WhatsAppConfig::webhookVerifyToken(), (string) $request->query('hub_verify_token'))
        ) {
            return response((string) $request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Meta POSTs delivery/read receipts (and inbound messages, unused here) to this URL.
     *
     * Signed with X-Hub-Signature-256 over the raw body using the app secret — verified so
     * a public, unauthenticated endpoint can't be used to forge delivery statuses.
     */
    public function webhookReceive(Request $request)
    {
        $secret = (string) config('services.meta.app_secret');
        if ($secret !== '') {
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, (string) $request->header('X-Hub-Signature-256', ''))) {
                Log::warning('[whatsapp webhook] signature mismatch');

                return response('', 403);
            }
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $this->applyDeliveryStatus($status);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function applyDeliveryStatus(array $status): void
    {
        $wamid = $status['id'] ?? null;
        $state = $status['status'] ?? null;

        if (!$wamid || !$state) {
            return;
        }

        $msg = WhatsAppMessage::where('provider_message_id', $wamid)->first();
        if (!$msg) {
            return;
        }

        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();

        if ($state === 'delivered') {
            // A late/duplicate 'delivered' event must not downgrade an already-'read' message.
            if ($msg->status !== 'read') {
                $msg->status = 'delivered';
            }
            $msg->delivered_at ??= $at;
            $msg->save();

            return;
        }

        if ($state === 'read') {
            $msg->status = 'read';
            $msg->delivered_at ??= $at;
            $msg->read_at ??= $at;
            $msg->save();

            return;
        }

        if ($state === 'failed') {
            $error = $status['errors'][0] ?? [];
            $msg->status        = 'failed';
            $msg->error_code    = $error['code'] ?? $msg->error_code;
            $msg->error_message = $error['title'] ?? ($error['message'] ?? $msg->error_message);
            $msg->save();

            if ((int) ($error['code'] ?? 0) === 131026 && $msg->user_id) {
                User::where('id', $msg->user_id)->update(['whatsapp_status' => 'invalid']);
            }
        }
    }

    /* ------------------------------------------------------------ linking to Meta */

    /**
     * Discover every WhatsApp Business Account and phone number this token can send from.
     *
     * The list comes from the token's own granular scopes rather than a hard-coded id, so
     * the screen always reflects what Meta actually grants — no guessing, no stale config.
     */
    public function linkDiscover()
    {
        if (!Auth::user()->can('manage whatsapp notification')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $token = WhatsAppConfig::token();
        if ($token === '') {
            return response()->json(['error' => __('Save the Meta access token first, then link.')], 422);
        }

        $appId     = (string) config('services.meta.app_id');
        $appSecret = (string) config('services.meta.app_secret');
        $client    = new \App\Services\WhatsApp\MetaCloudClient();

        $dbg = $client->debugToken($appId, $appSecret);
        if (!($dbg['success'] ?? false)) {
            return response()->json(['error' => $dbg['error'] ?? __('Could not verify the token.')], 422);
        }

        $wabaIds = [];
        foreach ($dbg['data']['granular_scopes'] ?? [] as $g) {
            if (($g['scope'] ?? '') === 'whatsapp_business_messaging') {
                $wabaIds = $g['target_ids'] ?? [];
                break;
            }
        }

        if (!$wabaIds) {
            return response()->json([
                'error' => __('This token does not grant access to any WhatsApp Business Account.'),
            ], 422);
        }

        $currentWaba  = WhatsAppConfig::wabaId();
        $currentPhone = WhatsAppConfig::phoneNumberId();
        $accounts     = [];

        foreach ($wabaIds as $wabaId) {
            $numbers = $client->get($wabaId . '/phone_numbers', [
                'fields' => 'id,display_phone_number,verified_name,status,quality_rating,platform_type',
            ]);
            $rows = $numbers['success'] ? ($numbers['data']['data'] ?? []) : [];
            if (!$rows) {
                continue; // an account with no numbers cannot send; don't clutter the list
            }

            $meta = $client->get($wabaId, ['fields' => 'name']);

            $accounts[] = [
                'waba_id' => $wabaId,
                'name'    => $meta['success'] ? ($meta['data']['name'] ?? $wabaId) : $wabaId,
                'numbers' => array_map(function ($p) use ($currentPhone) {
                    // Only a CONNECTED Cloud API number can send; anything else is listed
                    // with the reason rather than silently hidden.
                    $connected = ($p['status'] ?? '') === 'CONNECTED'
                        && ($p['platform_type'] ?? '') === 'CLOUD_API';

                    return [
                        'phone_number_id' => $p['id'] ?? '',
                        'display'         => $p['display_phone_number'] ?? '',
                        'verified_name'   => $p['verified_name'] ?? '',
                        'status'          => $p['status'] ?? '',
                        'platform'        => $p['platform_type'] ?? '',
                        'quality'         => $p['quality_rating'] ?? '',
                        'usable'          => $connected,
                        'reason'          => $connected ? '' : self::unusableReason($p),
                        'current'         => ($p['id'] ?? '') === $currentPhone,
                    ];
                }, $rows),
            ];
        }

        return response()->json([
            'accounts'     => $accounts,
            'current_waba' => $currentWaba,
            'template'     => WhatsAppConfig::template(),
            'language'     => WhatsAppConfig::language(),
        ]);
    }

    private static function unusableReason(array $p): string
    {
        $status   = $p['status'] ?? '';
        $platform = $p['platform_type'] ?? '';

        if ($status === 'PENDING') {
            return __('Not registered on the Cloud API yet — finish its setup in WhatsApp Manager.');
        }
        if ($status === 'DISCONNECTED') {
            return __('Disconnected.');
        }
        if ($platform === 'ON_PREMISE') {
            return __('Runs on the old On-Premise API, which this integration does not use.');
        }

        return trim($status . ' / ' . $platform, ' /');
    }

    /**
     * Bind the ERP to a chosen number, then immediately re-verify end to end.
     *
     * The template check in the response matters: templates belong to a single WABA, so
     * moving the sender to another account leaves the approved template behind and every
     * send would fail with 132001. The UI uses this flag to offer creating it there.
     */
    public function linkSelect(Request $request)
    {
        if (!Auth::user()->can('manage whatsapp notification')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $wabaId  = preg_replace('/\D/', '', (string) $request->input('waba_id'));
        $phoneId = preg_replace('/\D/', '', (string) $request->input('phone_number_id'));

        if ($wabaId === '' || $phoneId === '') {
            return response()->json(['error' => __('Pick an account and a number first.')], 422);
        }

        $client = new \App\Services\WhatsApp\MetaCloudClient();

        // Never trust the browser: confirm the number really belongs to that account.
        $numbers = $client->get($wabaId . '/phone_numbers', ['fields' => 'id,status,platform_type']);
        if (!($numbers['success'] ?? false)) {
            return response()->json(['error' => $numbers['error'] ?? __('Could not read the account.')], 422);
        }

        $match = null;
        foreach ($numbers['data']['data'] ?? [] as $p) {
            if (($p['id'] ?? '') === $phoneId) {
                $match = $p;
                break;
            }
        }

        if (!$match) {
            return response()->json(['error' => __('That number does not belong to this account.')], 422);
        }

        if (($match['status'] ?? '') !== 'CONNECTED' || ($match['platform_type'] ?? '') !== 'CLOUD_API') {
            return response()->json(['error' => self::unusableReason($match)], 422);
        }

        WhatsAppConfig::put('whatsapp_waba_id', $wabaId);
        WhatsAppConfig::put('whatsapp_phone_number_id', $phoneId);
        WhatsAppConfig::put('whatsapp_paused_until', '');
        WhatsAppConfig::flush();

        // Does the approved template exist on the account we just switched to?
        $tplName  = WhatsAppConfig::template();
        $tplLang  = WhatsAppConfig::language();
        $tplRes   = $client->get($wabaId . '/message_templates', ['name' => $tplName]);
        $tplState = 'missing';

        foreach ($tplRes['data']['data'] ?? [] as $t) {
            if (($t['name'] ?? '') === $tplName && ($t['language'] ?? '') === $tplLang) {
                $tplState = strtolower($t['status'] ?? 'unknown');
                break;
            }
        }

        $doctor = new \App\Services\WhatsApp\ConnectionDoctor(remoteOnly: true);
        $checks = $doctor->run();

        return response()->json([
            'success'        => true,
            'message'        => __('Linked. The sender number has been saved.'),
            'template_state' => $tplState,
            'template_name'  => $tplName,
            'needs_template' => $tplState !== 'approved',
            'checks'         => $checks,
            'summary'        => $doctor->summary(),
        ]);
    }

    /** Create the task template on the currently linked account. */
    public function linkCreateTemplate()
    {
        if (!Auth::user()->can('manage whatsapp notification')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $waba = WhatsAppConfig::wabaId();
        if ($waba === '') {
            return response()->json(['error' => __('Link an account first.')], 422);
        }

        $res = (new \App\Services\WhatsApp\MetaCloudClient())->createTaskTemplate(
            $waba,
            WhatsAppConfig::template(),
            WhatsAppConfig::language(),
            rtrim(config('app.url') ?: 'https://erp.my-communication.uk', '/')
        );

        if (!($res['success'] ?? false)) {
            return response()->json(['error' => $res['error'] ?? __('Could not create the template.')], 422);
        }

        return response()->json([
            'success' => true,
            'status'  => $res['status'],
            'message' => __('Template submitted to Meta. Approval usually takes a few minutes.'),
        ]);
    }

    /* ---------------------------------------------------------------- recipients */

    public function recipients(Request $request)
    {
        if (!Auth::user()->can('manage whatsapp recipient')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $query = User::where('created_by', Auth::user()->creatorId())
            ->where('type', '!=', 'client');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        if ($request->input('filter') === 'missing') {
            $query->where(function ($q) {
                $q->whereNull('whatsapp')->orWhere('whatsapp', '');
            });
        } elseif ($request->input('filter') === 'invalid') {
            $query->where('whatsapp_status', 'invalid');
        }

        $users = $query->orderBy('name')->paginate(50)->withQueryString();

        $stats = [
            'total'   => User::where('created_by', Auth::user()->creatorId())->where('type', '!=', 'client')->count(),
            'with'    => User::where('created_by', Auth::user()->creatorId())->where('type', '!=', 'client')
                ->whereNotNull('whatsapp')->where('whatsapp', '!=', '')->count(),
            'invalid' => User::where('created_by', Auth::user()->creatorId())->where('whatsapp_status', 'invalid')->count(),
        ];

        return view('whatsapp.recipients', compact('users', 'stats'));
    }

    public function updateRecipient(Request $request, $id)
    {
        if (!Auth::user()->can('manage whatsapp recipient')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $user = User::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$user) {
            return response()->json(['error' => __('User not found.')], 404);
        }

        $raw = trim((string) $request->input('whatsapp'));

        if ($raw === '') {
            $user->whatsapp = null;
            $user->whatsapp_status = 'unset';
            $user->save();

            return response()->json(['success' => true, 'whatsapp' => '', 'status' => 'unset']);
        }

        $e164 = PhoneNormalizer::e164($raw);
        if (!$e164) {
            return response()->json([
                'error' => __('Invalid number. Use the international format, e.g. +962791234567.'),
            ], 422);
        }

        // Storing the same number under two people means one of them silently never hears
        // about their tasks — surface it instead.
        $clash = User::where('whatsapp', $e164)->where('id', '!=', $user->id)->first();
        if ($clash) {
            return response()->json([
                'error' => __('This number is already assigned to') . ' ' . $clash->name,
            ], 422);
        }

        $user->whatsapp = $e164;
        // A changed number invalidates whatever the last send result proved.
        $user->whatsapp_status = 'unset';
        $user->save();

        return response()->json(['success' => true, 'whatsapp' => $e164, 'status' => 'unset']);
    }

    /**
     * Preview what an import from `employees.phone` would do.
     *
     * Not a data migration: that column holds 8 copies of the placeholder +971501234567 and
     * 4 of +1234567890, plus rows with several comma-separated numbers. The admin approves
     * the mapping before anything is written.
     */
    public function previewImport()
    {
        if (!Auth::user()->can('manage whatsapp recipient')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $rows = [];
        $employees = Employee::whereNotNull('phone')->where('phone', '!=', '')
            ->where('created_by', Auth::user()->creatorId())->get();

        foreach ($employees as $emp) {
            $user = User::find($emp->user_id);
            if (!$user || $user->created_by != Auth::user()->creatorId()) {
                continue;
            }
            if (!empty($user->whatsapp)) {
                continue; // never overwrite a number an admin already curated
            }

            $e164 = PhoneNormalizer::e164($emp->phone);

            $rows[] = [
                'user_id' => $user->id,
                'name'    => $user->name,
                'raw'     => $emp->phone,
                'e164'    => $e164,
                'ok'      => (bool) $e164,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['ok'] <=> $a['ok']) ?: strcmp($a['name'], $b['name']));

        return response()->json([
            'rows'    => $rows,
            'valid'   => count(array_filter($rows, fn ($r) => $r['ok'])),
            'invalid' => count(array_filter($rows, fn ($r) => !$r['ok'])),
        ]);
    }

    public function runImport(Request $request)
    {
        if (!Auth::user()->can('manage whatsapp recipient')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $imported = 0;
        $seen = [];

        $employees = Employee::whereNotNull('phone')->where('phone', '!=', '')
            ->where('created_by', Auth::user()->creatorId())->get();

        foreach ($employees as $emp) {
            $user = User::find($emp->user_id);
            if (!$user || $user->created_by != Auth::user()->creatorId() || !empty($user->whatsapp)) {
                continue;
            }

            $e164 = PhoneNormalizer::e164($emp->phone);
            if (!$e164 || isset($seen[$e164])) {
                continue; // skip junk and duplicated placeholders
            }

            if (User::where('whatsapp', $e164)->exists()) {
                continue;
            }

            $seen[$e164] = true;
            $user->whatsapp = $e164;
            $user->whatsapp_status = 'unset';
            $user->save();
            $imported++;
        }

        return redirect()->route('whatsapp.recipients')
            ->with('success', __('Imported numbers:') . ' ' . $imported);
    }

    /**
     * The only real way to find out whether a number is reachable on WhatsApp — Meta has no
     * pre-send lookup, so a live send returning 131026 is the sole source of truth.
     */
    public function testSend(Request $request, $id)
    {
        if (!Auth::user()->can('manage whatsapp recipient')) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $user = User::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$user || empty($user->whatsapp)) {
            return response()->json(['error' => __('This user has no WhatsApp number.')], 422);
        }

        if (!WhatsAppConfig::configured()) {
            return response()->json(['error' => __('WhatsApp is not configured yet.')], 422);
        }

        $messageId = app(TaskNotifier::class)->test($user->id);

        if (!$messageId) {
            return response()->json(['error' => __('Could not queue the test message.')], 422);
        }

        return response()->json([
            'success' => true,
            'message' => WhatsAppConfig::dryRun()
                ? __('Queued in dry-run mode — no real message was sent.')
                : __('Queued. It will be delivered within a minute.'),
            'id' => $messageId,
        ]);
    }

    /* ---------------------------------------------------------------------- log */

    public function log(Request $request)
    {
        if (!Auth::user()->can('view whatsapp log')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $companyId = Auth::user()->creatorId();

        $query = WhatsAppMessage::forCompany($companyId)->with(['user', 'task', 'project']);

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

        $base = WhatsAppMessage::forCompany($companyId);
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

        $users = User::where('created_by', $companyId)->orderBy('name')->pluck('name', 'id');

        return view('whatsapp.log', compact('messages', 'stats', 'users'));
    }

    public function resend($id)
    {
        if (!Auth::user()->can('view whatsapp log')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $msg = WhatsAppMessage::forCompany(Auth::user()->creatorId())->find($id);
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

    /** Read-only panel embedded in the task detail modal. */
    public function taskTab($taskId)
    {
        if (!Auth::user()->can('view whatsapp log')) {
            return response('', 204);
        }

        $task = ProjectTask::find($taskId);
        if (!$task) {
            return response('', 204);
        }

        $messages = WhatsAppMessage::where('task_id', $taskId)
            ->with('user')->orderByDesc('id')->limit(50)->get();

        return view('whatsapp.partials.task_tab', compact('task', 'messages'));
    }
}
