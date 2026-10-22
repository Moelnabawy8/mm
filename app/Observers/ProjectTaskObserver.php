<?php

namespace App\Observers;

use App\Models\ProjectTask;
use App\Models\User;
use App\Support\Telegram\TelegramConfig;
use App\Support\Telegram\TelegramGate;
use App\Support\Telegram\TelegramNotifier;
use App\Support\WhatsApp\TaskNotifier;
use App\Support\WhatsApp\WhatsAppConfig;
use App\Support\WhatsApp\WhatsAppGate;
use Illuminate\Support\Facades\Auth;

/**
 * Catches task assignment on EVERY code path.
 *
 * Observers rather than per-controller calls because every write funnels through
 * create()/update()/save(): ProjectTaskController@store, ApiController@createProjectTask,
 * Api\TaskApiController@createProjectTask, Integration\N8nApiController@createTask,
 * @bulkCreateTasks and @assignTaskUsers. Adding calls to six controllers would guarantee
 * that the seventh one someone writes next month is missed.
 *
 * HARD RULE: this class must never write to `project_tasks` or `task_comments`. It only
 * inserts into `whatsapp_messages`. That is what makes observer recursion structurally
 * impossible — do not "helpfully" add something like
 * $task->update(['last_notified_at' => now()]) here.
 */
class ProjectTaskObserver
{
    public function created(ProjectTask $task): void
    {
        $this->telegramCreated($task);

        if (WhatsAppGate::suppressed() || !WhatsAppConfig::enabled()) {
            return;
        }
        if (!WhatsAppConfig::eventEnabled('assign')) {
            return;
        }

        $ids = TaskNotifier::ids($task->assign_to);
        if (!$ids) {
            return;
        }

        WhatsAppGate::count(count($ids));
        app(TaskNotifier::class)->assigned($task, $ids, $this->actor());
    }

    public function updated(ProjectTask $task): void
    {
        $this->telegramUpdated($task);

        if (WhatsAppGate::suppressed() || !WhatsAppConfig::enabled()) {
            return;
        }

        // getChanges() only lists columns that actually changed in this save, so the kanban
        // reorder loop (which touches `order` on every task in a column) and plain
        // updated_at touches never get past here.
        $changed = array_keys($task->getChanges());

        // `is_complete` is deliberately NOT in this list: taskOrderUpdate and the API both
        // set it together with stage_id in one save(), so keying on stage_id alone yields
        // exactly one event instead of two.
        if (!array_intersect($changed, ['assign_to', 'stage_id'])) {
            return;
        }

        $actor = $this->actor();

        if (in_array('assign_to', $changed, true) && WhatsAppConfig::eventEnabled('assign')) {
            $old   = TaskNotifier::ids($task->getOriginal('assign_to'));
            $new   = TaskNotifier::ids($task->assign_to);
            $added = array_values(array_diff($new, $old)); // only newly added people

            if ($added) {
                WhatsAppGate::count(count($added));
                app(TaskNotifier::class)->assigned($task, $added, $actor);
            }
        }

        if (in_array('stage_id', $changed, true) && WhatsAppConfig::eventEnabled('stage')) {
            // Don't tell people about something they just did themselves.
            $recipients = array_values(array_diff(
                TaskNotifier::ids($task->assign_to),
                array_filter([$actor?->id])
            ));

            if ($recipients) {
                WhatsAppGate::count(count($recipients));
                app(TaskNotifier::class)->stageChanged(
                    $task,
                    $recipients,
                    $task->getOriginal('stage_id') ? (int) $task->getOriginal('stage_id') : null,
                    $task->stage_id ? (int) $task->stage_id : null,
                    $actor
                );
            }
        }
    }

    /**
     * Auth::user() is null for every n8n route (token auth, no session) and every CLI run,
     * so it is never dereferenced without a null check.
     */
    private function actor(): ?User
    {
        return Auth::check() ? Auth::user() : null;
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

    private function telegramCreated(ProjectTask $task): void
    {
        try {
            if (self::telegramSuppressed() || !TelegramConfig::eventEnabled('assign')) {
                return;
            }

            $ids = TelegramNotifier::ids($task->assign_to);
            if ($ids) {
                TelegramGate::count(count($ids));
                app(TelegramNotifier::class)->assigned($task, $ids, $this->actor());
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function telegramUpdated(ProjectTask $task): void
    {
        try {
            if (self::telegramSuppressed()) {
                return;
            }

            $changed = array_keys($task->getChanges());
            if (!array_intersect($changed, ['assign_to', 'stage_id'])) {
                return;
            }

            $actor = $this->actor();

            if (in_array('assign_to', $changed, true) && TelegramConfig::eventEnabled('assign')) {
                $old   = TelegramNotifier::ids($task->getOriginal('assign_to'));
                $new   = TelegramNotifier::ids($task->assign_to);
                $added = array_values(array_diff($new, $old)); // only newly added people

                if ($added) {
                    TelegramGate::count(count($added));
                    app(TelegramNotifier::class)->assigned($task, $added, $actor);
                }
            }

            if (in_array('stage_id', $changed, true) && TelegramConfig::eventEnabled('stage')) {
                // Don't tell people about something they just did themselves.
                $recipients = array_values(array_diff(
                    TelegramNotifier::ids($task->assign_to),
                    array_filter([$actor?->id])
                ));

                if ($recipients) {
                    TelegramGate::count(count($recipients));
                    app(TelegramNotifier::class)->stageChanged(
                        $task,
                        $recipients,
                        $task->getOriginal('stage_id') ? (int) $task->getOriginal('stage_id') : null,
                        $task->stage_id ? (int) $task->stage_id : null,
                        $actor
                    );
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
