<?php

namespace App\Services\Ai;

use App\Models\Utility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * One call to the model, with the ERP's own data available to it as tools.
 *
 * What this adds to the assistant that already existed: it could describe where screens
 * are, but knew nothing about what was in them, so "how many tasks are late in Design"
 * got a tour of the menu. Now the model may call the read-only lookups in ErpDataTools
 * and answer from the actual figures.
 *
 * Three things the old one had no answer for, handled here:
 *
 *   KEY. It read env('OPENAI_API_KEY') directly. env() returns null once config is
 *   cached, which is the normal state of a deployed Laravel app, so the feature could
 *   stop working on a deploy that changed nothing about it. The key is read from config,
 *   then the settings table, then env as a last resort.
 *
 *   SPEND. There was no limit of any kind on a paid API reachable by all 124 users.
 *   Every call is metered into ai_usage_logs and refused past a per-user monthly cap.
 *
 *   MODEL. It hardcoded gpt-4o-mini while the settings table said gpt-4o. The setting
 *   wins now, so changing it does not need a deploy.
 */
class AiAssistant
{
    /** How many tool round-trips one question may take before we stop. */
    private const MAX_TOOL_ROUNDS = 3;

    /** Conversation turns sent as context. Older ones cost money and add little. */
    private const HISTORY_TURNS = 12;

    public function __construct(private ErpDataTools $tools)
    {
    }

    public function enabled(): bool
    {
        $v = trim((string) Utility::getValByName('ai_enabled'));

        // Absent means on: the assistant already existed and was in use, so a missing
        // setting must not silently switch it off on deploy.
        return $v === '' || in_array(strtolower($v), ['1', 'on', 'yes', 'true'], true);
    }

    public function apiKey(): string
    {
        foreach ([
            (string) config('services.openai.key', ''),
            (string) Utility::getValByName('chat_gpt_key'),
            (string) env('OPENAI_API_KEY', ''),
        ] as $candidate) {
            $candidate = trim($candidate);

            // The settings table currently holds the literal string "none"; treat any
            // value that is not key-shaped as absent rather than sending it to OpenAI.
            if ($candidate !== '' && str_starts_with($candidate, 'sk-') && strlen($candidate) > 20) {
                return $candidate;
            }
        }

        return '';
    }

    public function model(): string
    {
        $m = trim((string) Utility::getValByName('chat_gpt_model'));

        return $m !== '' ? $m : 'gpt-4o-mini';
    }

    /** Questions one user may ask per calendar month. 0 means unlimited. */
    public function monthlyLimit(): int
    {
        $v = trim((string) Utility::getValByName('ai_monthly_limit'));

        return $v === '' ? 100 : max(0, (int) $v);
    }

    public function usedThisMonth(?int $userId = null): int
    {
        if (!Schema::hasTable('ai_usage_logs')) {
            return 0;
        }

        return DB::table('ai_usage_logs')
            ->where('user_id', $userId ?: Auth::id())
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function remainingThisMonth(?int $userId = null): ?int
    {
        $limit = $this->monthlyLimit();

        return $limit === 0 ? null : max(0, $limit - $this->usedThisMonth($userId));
    }

    /**
     * Answer one question.
     *
     * Returns ['ok' => bool, 'message' => string, 'tools_used' => string[]].
     * Never throws: the caller is an XHR from a chat box, and a stack trace in a chat
     * bubble helps nobody.
     */
    public function ask(string $question, array $history = []): array
    {
        if (!$this->enabled()) {
            return $this->fail(__('The AI assistant is switched off. An administrator can turn it on in settings.'));
        }

        $key = $this->apiKey();

        if ($key === '') {
            return $this->fail(__('No OpenAI key is configured, so the assistant cannot answer. An administrator needs to set one.'));
        }

        $limit = $this->monthlyLimit();

        if ($limit > 0 && $this->usedThisMonth() >= $limit) {
            return $this->fail(__('You have used your monthly allowance of AI questions.') . ' (' . $limit . ')');
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            array_slice($history, -self::HISTORY_TURNS),
            [['role' => 'user', 'content' => $question]]
        );

        $used = [];

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->call($key, $messages, $round < self::MAX_TOOL_ROUNDS);

            if (!$response['ok']) {
                return $this->fail($response['message']);
            }

            $choice = $response['choice'];
            $calls  = $choice['message']['tool_calls'] ?? [];

            if (!$calls) {
                $this->meter($response['usage'], $used);

                return [
                    'ok'         => true,
                    'message'    => trim((string) ($choice['message']['content'] ?? '')),
                    'tools_used' => $used,
                ];
            }

            // Echo the assistant turn back before the results, as the API requires.
            $messages[] = $choice['message'];

            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true) ?: [];

                $used[] = $name;

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content'      => json_encode($this->tools->run($name, $args), JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        return $this->fail(__('That question needed too many lookups. Try asking it more simply.'));
    }

    /* --------------------------------------------------------------- internals */

    private function call(string $key, array $messages, bool $allowTools): array
    {
        $payload = [
            'model'       => $this->model(),
            'messages'    => $messages,
            'max_tokens'  => 900,
            'temperature' => 0.2,   // figures, not prose: keep it literal
        ];

        if ($allowTools) {
            $payload['tools']       = $this->tools->definitions();
            $payload['tool_choice'] = 'auto';
        }

        try {
            $res = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => __('Could not reach the AI service. Try again in a moment.')];
        }

        if ($res->failed()) {
            $message = match ($res->status()) {
                401     => __('The OpenAI key is not valid.'),
                429     => __('The AI service is rate limited or out of credit. Try again later.'),
                default => __('The AI service returned an error.') . ' (' . $res->status() . ')',
            };

            return ['ok' => false, 'message' => $message];
        }

        $data = $res->json();

        if (!isset($data['choices'][0])) {
            return ['ok' => false, 'message' => __('The AI service returned nothing usable.')];
        }

        return ['ok' => true, 'choice' => $data['choices'][0], 'usage' => $data['usage'] ?? []];
    }

    /** One row per answered question, so spend is visible before the invoice is. */
    private function meter(array $usage, array $toolsUsed): void
    {
        if (!Schema::hasTable('ai_usage_logs')) {
            return;
        }

        try {
            DB::table('ai_usage_logs')->insert([
                'user_id'       => Auth::id(),
                'created_by'    => Auth::user()->creatorId(),
                'model'         => $this->model(),
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'tools_used'    => $toolsUsed ? implode(',', array_unique($toolsUsed)) : null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // Metering must never cost the user their answer.
            report($e);
        }
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'tools_used' => []];
    }

    private function systemPrompt(): string
    {
        $user = Auth::user();
        $name = $user->name ?? 'the user';
        $date = now()->toDateString();

        return <<<PROMPT
You are the assistant inside an ERP used by a marketing and software company. You are
talking to {$name}. Today is {$date}.

You can do two things:

1. ANSWER FROM THE SYSTEM'S OWN DATA by calling the provided tools. Prefer this whenever
   the question is about what is actually in the system — counts, who is late, how a
   department is doing, what needs writing up. Call a tool rather than guessing, and
   never invent a number. If a tool returns an error or an empty result, say so plainly.

2. EXPLAIN HOW TO USE THE SYSTEM when the question is about where something is or how to
   do it. Key locations:
   - Tasks and projects: /projects, /tasks
   - Quality by department: /quality
   - Commitments (what people owe each other): /commitments/mine and /commitments/owed-to-me
   - Meeting minutes: /meeting-minutes
   - Inactive accounts report: /report/inactivity
   - Employees: /employee   Attendance: /attendanceemployee   Leave: /leave
   - Leads: /leads   Deals: /deals   Invoices: /invoice   Payroll: /payslip

Rules:
- Reply in the language the user wrote in. Most users here write Arabic; answer in Arabic
  when they do, and keep numbers and dates in Latin digits.
- Be brief. A number and one sentence beats a paragraph.
- Never claim to have changed anything. You can only read.
- If the user asks for something outside these tools, say what you cannot do and point
  them at the screen that can.
PROMPT;
    }
}
