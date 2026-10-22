<?php

namespace App\Observers;

use App\Models\ProjectTask;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Telegram\TelegramConfig;
use App\Support\Telegram\TelegramGate;
use App\Support\Telegram\TelegramNotifier;
use App\Support\WhatsApp\TaskNotifier;
use App\Support\WhatsApp\WhatsAppConfig;
use App\Support\WhatsApp\WhatsAppGate;
use Illuminate\Support\Facades\Auth;

/**
 * Notifies a task's assignees when someone comments on it.
 *
 * The suppression scope matters most here: ProjectController@duplicate copies every comment
 * of every task with `new TaskComment(); ->save()`, so a 50-task project with 10 comments
 * each would fire this 500 times.
 *
 * HARD RULE: never write to `task_comments` or `project_tasks` from this class.
 */
class TaskCommentObserver
{
    public function created(TaskComment $comment): void
    {
        $this->telegramCommented($comment);

        if (WhatsAppGate::suppressed() || !WhatsAppConfig::enabled()) {
            return;
        }
        if (!WhatsAppConfig::eventEnabled('comment')) {
            return;
        }

        $task = ProjectTask::find($comment->task_id);
        if (!$task) {
            return;
        }

        $actor = Auth::check() ? Auth::user() : $this->authorFrom($comment);

        $recipients = array_values(array_diff(
            TaskNotifier::ids($task->assign_to),
            array_filter([$actor?->id])
        ));

        if (!$recipients) {
            return;
        }

        WhatsAppGate::count(count($recipients));

        app(TaskNotifier::class)->commented(
            $task,
            $recipients,
            (int) $comment->id,
            (string) $comment->comment,
            $actor
        );
    }

    /** TaskComment::user() keys off created_by, so fall back to that outside a web session. */
    private function authorFrom(TaskComment $comment): ?User
    {
        $id = $comment->user_id ?: $comment->created_by;

        return $id ? User::find($id) : null;
    }

    // ── Telegram task notifications ─────────────────────────────────────────────
    // Independent channel with its own config. Called as the FIRST statement of the
    // WhatsApp hooks so their early returns can never skip it, and wrapped so a
    // Telegram failure can never break the save or the WhatsApp path. Same HARD RULE
    // as the class docblock: only ever inserts into `telegram_messages`.
    //
    // Also honours WhatsApp's suppression scope: the project-duplication actions
    // (ProjectController@duplicate, ApiController@duplicateProject) only call
    // WhatsAppGate::suppressForRequest(), and a request that silenced WhatsApp for a
    // bulk clone is a bulk clone for Telegram too.
    private static function telegramSuppressed(): bool
    {
        return TelegramGate::suppressed() || WhatsAppGate::suppressed() || !TelegramConfig::enabled();
    }

    private function telegramCommented(TaskComment $comment): void
    {
        try {
            if (self::telegramSuppressed() || !TelegramConfig::eventEnabled('comment')) {
                return;
            }

            $task = ProjectTask::find($comment->task_id);
            if (!$task) {
                return;
            }

            $actor = Auth::check() ? Auth::user() : $this->authorFrom($comment);

            $recipients = array_values(array_diff(
                TelegramNotifier::ids($task->assign_to),
                array_filter([$actor?->id])
            ));

            if ($recipients) {
                TelegramGate::count(count($recipients));

                app(TelegramNotifier::class)->commented(
                    $task,
                    $recipients,
                    (int) $comment->id,
                    (string) $comment->comment,
                    $actor
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
