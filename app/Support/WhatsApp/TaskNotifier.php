<?php

namespace App\Support\WhatsApp;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single place that renders parameters and writes outbox rows.
 *
 * Both observers, the reminder command and the admin test-send all funnel through here, so
 * the sanitizer and the dedupe scheme each exist in exactly one place.
 *
 * Everything maps onto ONE approved Meta template (`task_alert_ar`) whose second parameter
 * is the event label — that is what keeps this to a single template approval instead of five.
 *
 *   {{1}} recipient name   {{2}} event label   {{3}} task name
 *   {{4}} project name     {{5}} date          {{6}} event detail
 *   button URL suffix: projects/{project_id}
 */
class TaskNotifier
{
    public const PRIORITY_AR = [
        'critical' => 'حرجة',
        'high'     => 'عالية',
        'medium'   => 'متوسطة',
        'low'      => 'منخفضة',
    ];

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
        if (!$user) {
            return null;
        }

        $phone = PhoneNormalizer::e164($user->whatsapp);
        if (!$phone) {
            return null;
        }

        $params = [
            Sanitizer::param($user->name, 60),
            Sanitizer::param('رسالة اختبار من نظام الـ ERP', 120),
            Sanitizer::param('اختبار الاتصال', 120),
            Sanitizer::param('My-Communication ERP', 80),
            Sanitizer::param(date('Y-m-d'), 30),
            Sanitizer::param('لو وصلتك الرسالة دي، الإعدادات سليمة', 200),
        ];

        return $this->insert(
            userId: $userId,
            phone: $phone,
            taskId: $task?->id,
            projectId: $task?->project_id,
            event: 'test',
            params: $params,
            urlSuffix: 'projects',
            dedupeKey: 'test:' . $userId . ':' . microtime(true),
            companyId: (int) (auth()->user()?->creatorId() ?? WhatsAppConfig::COMPANY_ID)
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

        $phone = PhoneNormalizer::e164($user->whatsapp);
        if (!$phone) {
            // Not silence: an admin needs to see who is unreachable and why.
            $this->logSkip($userId, $task, $event, 'no_whatsapp_number');

            return;
        }

        $project = $task->relationLoaded('project')
            ? $task->project
            : Project::find($task->project_id);

        $params = [
            Sanitizer::param($user->name, 60),
            Sanitizer::param($label, 120),
            Sanitizer::param($task->name, 120),
            Sanitizer::param($project->project_name ?? 'غير محدد', 80),
            Sanitizer::param($this->date($task->end_date), 30, 'غير محدد'),
            Sanitizer::param($detail, 200),
        ];

        $this->insert(
            userId: $userId,
            phone: $phone,
            taskId: (int) $task->id,
            projectId: $task->project_id ? (int) $task->project_id : null,
            event: $event,
            params: $params,
            urlSuffix: $task->project_id ? 'projects/' . $task->project_id : 'projects',
            dedupeKey: $dedupeKey,
            // The company owns the row, NOT Auth::id() — n8n calls have no session and
            // would otherwise write created_by = 0 and vanish from the company log page.
            companyId: (int) ($task->created_by ?: WhatsAppConfig::COMPANY_ID)
        );
    }

    private function insert(
        int $userId,
        string $phone,
        ?int $taskId,
        ?int $projectId,
        string $event,
        array $params,
        string $urlSuffix,
        string $dedupeKey,
        int $companyId
    ): ?int {
        // ON DUPLICATE KEY UPDATE rather than insertOrIgnore: counting suppressed re-fires
        // is what lets the log answer "did anyone silently miss a message?".
        DB::statement(
            'INSERT INTO whatsapp_messages
                (user_id, phone, task_id, project_id, event_type, template_name, language,
                 payload, body_preview, status, attempts, duplicate_hits, next_attempt_at,
                 dedupe_key, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, 0, NOW(), ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE duplicate_hits = duplicate_hits + 1, updated_at = NOW()',
            [
                $userId,
                $phone,
                $taskId,
                $projectId,
                $event,
                WhatsAppConfig::template(),
                WhatsAppConfig::language(),
                // JSON_UNESCAPED_UNICODE matters: without it the stored payload is
                // مر... and the log page is unreadable for Arabic content.
                json_encode(['params' => $params, 'url_suffix' => $urlSuffix], JSON_UNESCAPED_UNICODE),
                Sanitizer::preview($params),
                $dedupeKey,
                $companyId,
            ]
        );

        return (int) DB::getPdo()->lastInsertId() ?: null;
    }

    private function logSkip(int $userId, ProjectTask $task, string $event, string $reason): void
    {
        Log::info('[whatsapp] skipped notification', [
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
