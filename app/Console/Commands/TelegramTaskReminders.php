<?php

namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Support\Telegram\TelegramConfig;
use App\Support\Telegram\TelegramGate;
use App\Support\Telegram\TelegramNotifier;
use Illuminate\Console\Command;

/**
 * Due-date reminders for open tasks. Runs once a day from cron.
 *
 * Independent twin of whatsapp:task-reminders — same two load-bearing safety rules:
 *
 * 1. Never touch overdue tasks. There are ~1169 open tasks with an end_date in the past;
 *    a "remind me about overdue work" pass would fire over a thousand messages in one
 *    run and get the bot rate-limited (429) or flagged for spam.
 *    Only end_date = today / tomorrow is considered.
 *
 * 2. Set the timezone explicitly. The OS is UTC; the company operates in Asia/Amman and the
 *    app only applies that setting inside the XSS middleware when Auth::check() is true —
 *    which is never the case in cron. Without this, "today" is wrong for three hours a day
 *    and lands on the wrong calendar date around midnight.
 */
class TelegramTaskReminders extends Command
{
    protected $signature = 'telegram:task-reminders
                            {--dry-run : list what would be enqueued without writing anything}
                            {--limit=50 : hard ceiling on tasks processed in one run}';

    protected $description = 'Enqueue Telegram reminders for tasks due today or tomorrow';

    public function handle(): int
    {
        TelegramGate::allowCli();
        TelegramConfig::flush();

        date_default_timezone_set(TelegramConfig::timezone());

        if (!TelegramConfig::enabled() && !$this->option('dry-run')) {
            $this->warn('Telegram notifications are disabled.');

            return self::SUCCESS;
        }

        if (!TelegramConfig::eventEnabled('due') && !$this->option('dry-run')) {
            $this->warn('Due-date reminders are turned off in settings.');

            return self::SUCCESS;
        }

        $offsets = TelegramConfig::dueOffsets();
        $limit   = max(1, (int) $this->option('limit'));
        $dry     = (bool) $this->option('dry-run');
        $notifier = app(TelegramNotifier::class);

        $dates = [];
        if (in_array('d0', $offsets, true)) {
            $dates['d0'] = date('Y-m-d');
        }
        if (in_array('d1', $offsets, true)) {
            $dates['d1'] = date('Y-m-d', strtotime('+1 day'));
        }

        $this->line('Timezone: ' . TelegramConfig::timezone() . '  |  today=' . date('Y-m-d'));

        $totalTasks = 0;
        $totalRows  = 0;

        foreach ($dates as $offset => $date) {
            $query = ProjectTask::whereDate('end_date', $date)
                ->where('is_complete', 0)
                ->whereNotNull('assign_to')
                ->where('assign_to', '!=', '');

            $count = (clone $query)->count();

            // A blown date filter is the one bug that could spam a thousand chats, so
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
                $ids = TelegramNotifier::ids($task->assign_to);
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
