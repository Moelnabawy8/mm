<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $expectedToken = (string) config('services.whatsapp.verify_token', '');

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, (string) $token)) {
            Log::info('WhatsApp webhook verified successfully.');
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verification failed.', [
            'mode' => $mode,
            'token_present' => !empty($token),
        ]);

        return response('Forbidden', 403);
    }

    /** Later states never get overwritten by an earlier one arriving out of order. */
    private const RANK = ['pending' => 0, 'sending' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    public function receive(Request $request)
    {
        // Raw body, not $request->all(): the global FilterRequest middleware strips 'alert'
        // and 'javascript' from every value, and the signature is computed over the raw bytes.
        $raw = $request->getContent();

        $secret = (string) config('services.meta.app_secret');
        if ($secret !== '') {
            $expected = 'sha256=' . hash_hmac('sha256', $raw, $secret);
            if (!hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
                Log::warning('[whatsapp] webhook signature mismatch — event ignored');

                return response('Forbidden', 403);
            }
        }

        $payload = json_decode($raw, true) ?: [];

        // Warning, not info: production runs LOG_LEVEL=error and info lines are dropped.
        Log::warning('WhatsApp webhook event received.', ['payload' => $payload]);

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $this->applyStatus($status);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    private function applyStatus(array $status): void
    {
        $msg = WhatsAppMessage::where('provider_message_id', (string) ($status['id'] ?? ''))->first();
        if (!$msg) {
            return; // another system's message on the same app, or a row we never sent
        }

        $state = (string) ($status['status'] ?? '');
        $at    = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();

        if ($state === 'failed') {
            $err  = $status['errors'][0] ?? [];
            $code = (int) ($err['code'] ?? 0);

            $msg->update([
                'status'        => 'failed',
                'error_code'    => $code ?: null,
                'error_message' => trim(($err['title'] ?? '') . ' ' . ($err['error_data']['details'] ?? '')) ?: null,
            ]);

            if ($code === 131026 && $msg->user_id) {
                User::where('id', $msg->user_id)->update(['whatsapp_status' => 'invalid']);
            }

            return;
        }

        if (!isset(self::RANK[$state]) || $msg->status === 'failed'
            || self::RANK[$state] <= (self::RANK[$msg->status] ?? -1)) {
            return;
        }

        $update = ['status' => $state];
        if ($state === 'delivered') {
            $update['delivered_at'] = $at;
        } elseif ($state === 'read') {
            $update['read_at'] = $at;
            $update['delivered_at'] = $msg->delivered_at ?? $at;
        }

        $msg->update($update);
    }
}
