<?php

namespace App\Observers;

use App\Models\ProjectTask;
use App\Models\TaskComment;
use App\Models\User;
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
}
