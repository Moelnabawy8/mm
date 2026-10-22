<?php

namespace App\Support\Telegram;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single place that renders Telegram bodies and writes outbox rows.
 *
 * FULLY INDEPENDENT from App\Support\WhatsApp\TaskNotifier by design: no shared
 * base class, no shared helpers, no imports from the WhatsApp namespace. The two
 * channels evolve separately (templates vs HTML, phone vs chat_id, webhook vs
 * long-polling), and a shared abstraction would couple their failure modes.
 * A few lines of look-alike code (priority map, CSV ids, date) are duplicated
 * on purpose — that is cheaper than the coupling.
 *
 * Both observers, the reminder command and the admin test-send all funnel through
 * here, so the HTML builder and the dedupe scheme each exist in exactly one place.
 */
class TelegramNotifier
{
    public const PRIORITY_AR = [
        'critical' => 'حرجة',
        'high'     => 'عالية',
        'medium'   => 'متوسطة',
        'low'      => 'منخفضة',
    ];

    /** Bot API hard ceiling per message. */
    public const MAX_LENGTH = 4000;

    /* ------------------------------------------------------------------ events */

    /** @param int[] $userIds */
    public function assigned(ProjectTask $task, array $userIds, ?User $actor = null): void
    {
        $isNew = $task->wasRecentlyCreated;

        foreach ($userIds as $uid) {
            $label  = $isNew ? 'تم إسناد مهمة جديدة إليك' : 'تمت إضافتك إلى مهمة';
            $detail = $isNew
                ? 'الأولوية: ' . $this->priority($task)
                : ($actor ? 'أضافك: ' . $actor->name : 'الأولوية: ' . $this->priority($task));

            $this->enqueue(
                userId: (int) $uid,
                task: $task,
                event: 'assign',
                dedupeKey: "assign:{$task->id}:{$uid}",
                label: $label,
                detail: $detail
            );
        }
    }

    /** @param int[] $userIds */
    public function stageChanged(
        ProjectTask $task,
        array $userIds,
        ?int $fromStageId,
        ?int $toStageId,
        ?User $actor = null
    ): void {
        $from = $this->stageName($fromStageId);
        $to   = $this->stageName($toStageId);
        $bucket = date('Ymd_H');

        foreach ($userIds as $uid) {
            $this->enqueue(
                userId: (int) $uid,
                task: $task,
                event: 'stage',
                dedupeKey: "stage:{$task->id}:{$uid}:{$toStageId}:{$bucket}",
                label: 'تم تحديث حالة المهمة',
                detail: "من: {$from} ← إلى: {$to}"
            );
        }
    }

    /** @param int[] $userIds */
    public function commented(ProjectTask $task, array $userIds, int $commentId, string $comment, ?User $actor = null): void
    {
        $who    = $actor?->name ?? 'أحد أعضاء الفريق';
        $detail = $who . ': ' . $comment;

        foreach ($userIds as $uid) {
            $this->enqueue(
                userId: (int) $uid,
                task: $task,
                event: 'comment',
                dedupeKey: "comment:{$commentId}:{$uid}",
                label: 'تعليق جديد على مهمة',
                detail: $detail
            );
        }
    }

    /**
     * @param int[]  $userIds
     * @param string $offset  'd1' (due tomorrow) or 'd0' (due today)
     */
    public function dueReminder(ProjectTask $task, array $userIds, string $offset): void
    {
        $label  = $offset === 'd0' ? 'تذكير: موعد تسليم مهمة اليوم' : 'تذكير: موعد تسليم مهمة غداً';
        $detail = $offset === 'd0' ? 'المتبقي: اليوم' : 'المتبقي: يوم واحد';
        $due    = $this->date($task->end_date);

        foreach ($userIds as $uid) {
            $this->enqueue(
                userId: (int) $uid,
                task: $task,
                event: 'due',
                dedupeKey: "due:{$task->id}:{$uid}:{$due}:{$offset}",
                label: $label,
                detail: $detail
            );
        }
    }

    /** Admin-triggered check from the recipients screen. Never deduped. */
    public function test(int $userId, ?ProjectTask $task = null): ?int
    {
        $user = User::find($userId);
        if (!$user || empty($user->telegram_chat_id)) {
            return null;
        }

        return $this->insert(
            userId: $userId,
            chatId: (string) $user->telegram_chat_id,
            taskId: $task?->id,
            projectId: $task?->project_id,
            event: 'test',
            label: 'رسالة اختبار من نظام الـ ERP',
            detail: 'لو وصلتك الرسالة دي، الإعدادات سليمة',
            taskName: 'اختبار الاتصال',
            projectName: 'My-Communication ERP',
            date: date('Y-m-d'),
            dedupeKey: 'test:' . $userId . ':' . microtime(true),
            companyId: (int) (auth()->user()?->creatorId() ?? TelegramConfig::COMPANY_ID)
        );
    }

    /* ------------------------------------------------------------------ internals */

    private function enqueue(
        int $userId,
        ProjectTask $task,
        string $event,
        string $dedupeKey,
        string $label,
        string $detail
    ): void {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        if (empty($user->telegram_chat_id)) {
            // Not silence: an admin needs to see who is unreachable and why.
            $this->logSkip($userId, $task, $event, 'no_telegram_chat');

            return;
        }

        $project = $task->relationLoaded('project')
            ? $task->project
            : Project::find($task->project_id);

        $this->insert(
            userId: $userId,
            chatId: (string) $user->telegram_chat_id,
            taskId: (int) $task->id,
            projectId: $task->project_id ? (int) $task->project_id : null,
            event: $event,
            label: $label,
            detail: $detail,
            taskName: (string) $task->name,
            projectName: (string) ($project->project_name ?? 'غير محدد'),
            date: $this->date($task->end_date),
            dedupeKey: $dedupeKey,
            // The company owns the row, NOT Auth::id() — n8n calls have no session and
            // would otherwise write created_by = 0 and vanish from the company log page.
            companyId: (int) ($task->created_by ?: TelegramConfig::COMPANY_ID)
        );
    }

    private function insert(
        int $userId,
        string $chatId,
        ?int $taskId,
        ?int $projectId,
        string $event,
        string $label,
        string $detail,
        string $taskName,
        string $projectName,
        string $date,
        string $dedupeKey,
        int $companyId
    ): ?int {
        $html    = $this->buildHtml($label, $taskName, $projectName, $date, $detail, $projectId);
        $preview = $this->preview($label, $taskName, $projectName, $date, $detail);

        // ON DUPLICATE KEY UPDATE rather than insertOrIgnore: counting suppressed re-fires
        // is what lets the log answer "did anyone silently miss a message?".
        DB::statement(
            'INSERT INTO telegram_messages
                (user_id, chat_id, task_id, project_id, event_type,
                 body_html, payload, body_preview, status, attempts, duplicate_hits, next_attempt_at,
                 dedupe_key, created_by, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, 0, NOW(), ?, ?, NOW(), NOW())
              ON DUPLICATE KEY UPDATE duplicate_hits = duplicate_hits + 1, updated_at = NOW()',
            [
                $userId,
                $chatId,
                $taskId,
                $projectId,
                $event,
                $html,
                // JSON_UNESCAPED_UNICODE matters: without it the stored payload is
                // مر... and the log page is unreadable for Arabic content.
                json_encode([
                    'label'   => $label,
                    'detail'  => $detail,
                    'app_url' => rtrim(config('app.url') ?: '', '/'),
                ], JSON_UNESCAPED_UNICODE),
                $preview,
                $dedupeKey,
                $companyId,
            ]
        );

        return (int) DB::getPdo()->lastInsertId() ?: null;
    }

    /**
     * Render the HTML body sent to Bot API. Every dynamic value is escaped —
     * Telegram parses <b> <i> <a> <code>, so a raw task name containing "<b>"
     * would break parsing (400 Bad Request: can't parse entities).
     */
    public function buildHtml(
        string $label,
        string $taskName,
        string $projectName,
        string $date,
        string $detail,
        ?int $projectId = null
    ): string {
        $e = fn (?string $v, int $max = 300): string => $this->esc($this->clip($v ?? '', $max));

        $lines = [
            '🔔 <b>' . $e($label, 120) . '</b>',
            '',
            '📌 المهمة: ' . $e($taskName, 150),
            '📁 المشروع: ' . $e($projectName, 100),
            '📅 التاريخ: ' . $e($date, 30),
            'ℹ️ ' . $e($detail, 500),
        ];

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            $href = $appUrl . ($projectId ? '/projects/' . $projectId : '/projects');
            $lines[] = '';
            $lines[] = '<a href="' . $this->esc($href) . '">فتح النظام</a>';
        }

        $html = implode("\n", $lines);

        if (mb_strlen($html) > self::MAX_LENGTH) {
            $html = mb_substr($html, 0, self::MAX_LENGTH - 1) . '…';
        }

        return $html;
    }

    /** Human-readable one-liner stored on the outbox row for the log UI. */
    private function preview(string $label, string $task, string $project, string $date, string $detail): string
    {
        $text = implode(' | ', array_filter([$label, $task, $project, $date, $detail], 'strlen'));
        $text = preg_replace('/\s+/u', ' ', trim($text));

        return $this->clip($text, 500);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function clip(string $value, int $max): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));

        // mb_* throughout: Arabic is multi-byte and substr() would split a codepoint,
        // producing invalid UTF-8 that Telegram rejects with a confusing 400.
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max - 1) . '…';
        }

        return $value === '' ? '—' : $value;
    }

    private function logSkip(int $userId, ProjectTask $task, string $event, string $reason): void
    {
        Log::info('[telegram] skipped notification', [
            'user_id' => $userId,
            'task_id' => $task->id,
            'event'   => $event,
            'reason'  => $reason,
        ]);
    }

    private function priority(ProjectTask $task): string
    {
        $p = strtolower((string) $task->priority);

        return self::PRIORITY_AR[$p] ?? ($task->priority ?: 'غير محددة');
    }

    private function stageName(?int $stageId): string
    {
        if (!$stageId) {
            return 'غير محددة';
        }

        return TaskStage::where('id', $stageId)->value('name') ?: 'غير محددة';
    }

    private function date($value): string
    {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return 'غير محدد';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return 'غير محدد';
        }
    }

    /**
     * Shared CSV parser for `project_tasks.assign_to` — a TEXT column of comma-separated ids
     * with no FK, no cast and no validation. Handles null, "", "0", " 3 , 7 ", [3,7] and dupes.
     *
     * @return int[]
     */
    public static function ids(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $value))
        )));
    }
}
