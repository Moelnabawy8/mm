<?php

namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Support\WhatsApp\TaskNotifier;
use App\Support\WhatsApp\WhatsAppConfig;
use App\Support\WhatsApp\WhatsAppGate;
use Illuminate\Console\Command;

/**
 * Due-date reminders for open tasks. Runs once a day from cron.
 *
 * TWO SAFETY RULES, both load-bearing:
 *
 * 1. Never touch overdue tasks. There are ~1169 open tasks with an end_date in the past;
 *    a "remind me about overdue work" pass would fire over a thousand template messages in
 *    one run, torch the sender's GREEN quality rating and get the number restricted.
 *    Only end_date = today / tomorrow is considered (currently ~5 tasks).
 *
 * 2. Set the timezone explicitly. The OS is UTC; the company operates in Asia/Amman and the
 *    app only applies that setting inside the XSS middleware when Auth::check() is true —
 *    which is never the case in cron. Without this, "today" is wrong for three hours a day
 *    and lands on the wrong calendar date around midnight.
 */
class WhatsAppTaskReminders extends Command
{
    protected $signature = 'whatsapp:task-reminders
                            {--dry-run : list what would be enqueued without writing anything}
                            {--limit=50 : hard ceiling on tasks processed in one run}';

    protected $description = 'Enqueue WhatsApp reminders for tasks due today or tomorrow';

    public function handle(): int
    {
        WhatsAppGate::allowCli();
        WhatsAppConfig::flush();

        date_default_timezone_set(WhatsAppConfig::timezone());

        if (!WhatsAppConfig::enabled() && !$this->option('dry-run')) {
            $this->warn('WhatsApp notifications are disabled.');

            return self::SUCCESS;
        }

        if (!WhatsAppConfig::eventEnabled('due') && !$this->option('dry-run')) {
            $this->warn('Due-date reminders are turned off in settings.');

            return self::SUCCESS;
        }

        $offsets = WhatsAppConfig::dueOffsets();
        $limit   = max(1, (int) $this->option('limit'));
        $dry     = (bool) $this->option('dry-run');
        $notifier = app(TaskNotifier::class);

        $dates = [];
        if (in_array('d0', $offsets, true)) {
            $dates['d0'] = date('Y-m-d');
        }
        if (in_array('d1', $offsets, true)) {
            $dates['d1'] = date('Y-m-d', strtotime('+1 day'));
        }

        $this->line('Timezone: ' . WhatsAppConfig::timezone() . '  |  today=' . date('Y-m-d'));

        $totalTasks = 0;
        $totalRows  = 0;

        foreach ($dates as $offset => $date) {
            $query = ProjectTask::whereDate('end_date', $date)
                ->where('is_complete', 0)
                ->whereNotNull('assign_to')
                ->where('assign_to', '!=', '');

            $count = (clone $query)->count();

            // A blown date filter is the one bug that could cost the sender its rating, so
            // it is surfaced loudly instead of being silently truncated.
            if ($count > $limit) {
                $this->error(sprintf(
                    'SAFETY STOP: %d tasks match %s (%s) but the limit is %d. '
                    . 'Expected a handful — check the date filter before continuing.',
                    $count, $offset, $date, $limit
                ));

                continue;
            }

            foreach ($query->limit($limit)->get() as $task) {
                $ids = TaskNotifier::ids($task->assign_to);
                if (!$ids) {
                    continue;
                }

                $totalTasks++;
                $totalRows += count($ids);

                if ($dry) {
                    $this->line(sprintf(
                        '  [%s] task #%d "%s" (due %s) -> users %s',
                        $offset, $task->id, mb_substr((string) $task->name, 0, 40), $date, implode(',', $ids)
                    ));

                    continue;
                }

                $notifier->dueReminder($task, $ids, $offset);
            }
        }

        $this->info(sprintf(
            '%s%d tasks, %d recipients.',
            $dry ? '[DRY RUN] ' : '',
            $totalTasks,
            $totalRows
        ));

        return self::SUCCESS;
    }
}
