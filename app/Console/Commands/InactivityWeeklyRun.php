<?php

namespace App\Console\Commands;

use App\Mail\InactivityReportMail;
use App\Services\InactivityAudit;
use App\Support\Inactivity\InactivityConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Weekly inactivity sweep: report who stopped logging in, and — only when the owner has
 * switched enforcement on — block their login.
 *
 * Driven by a direct crontab line for the site user, NOT Laravel's scheduler: there is no
 * `schedule:run` entry on this server, so anything registered in Kernel::schedule() never
 * fires. See the WhatsApp cron entries for the same pattern.
 */
class InactivityWeeklyRun extends Command
{
    protected $signature = 'inactivity:weekly-run
                            {--dry-run : Report only; never write a ban even if enforcement is on}
                            {--no-mail : Skip the email}';

    protected $description = 'Report inactive accounts and (when enforcing) block their login';

    public function handle(InactivityAudit $audit): int
    {
        $report    = $audit->scan();
        $dryRun    = (bool) $this->option('dry-run');
        $enforcing = $report['enforcing'] && !$dryRun;

        $this->line(sprintf(
            '[%s] threshold=%dd grace=%dd mode=%s',
            date('Y-m-d H:i:s'),
            $report['threshold'],
            $report['grace'],
            $dryRun ? 'DRY-RUN' : ($report['enforcing'] ? 'ENFORCE' : 'REPORT-ONLY')
        ));

        $this->table(
            [__('Bucket'), __('Count')],
            [
                ['candidates', count($report['candidates'])],
                ['banned (already)', count($report['banned'])],
                ['warning', count($report['warning'])],
                ['exempt', count($report['exempt'])],
                ['active', count($report['active'])],
            ]
        );

        $banned = 0;
        if ($enforcing) {
            // صمّام أمان: لو فاق عدد المرشّحين السقف، لا نحظر أحدًا ونشرح السبب.
            // خطأ واحد في قائمة الاستثناءات كفيل بإقفال الشركة كلها؛ هذا السطر يمنعه.
            $cap = InactivityConfig::maxBansPerRun();
            if (count($report['candidates']) > $cap) {
                $this->error(sprintf(
                    'SAFETY STOP: %d accounts match but the per-run cap is %d. Nothing was banned. '
                    . 'Raise inactivity_max_bans_per_run deliberately if this is expected.',
                    count($report['candidates']),
                    $cap
                ));
                if (!$this->option('no-mail')) {
                    $this->sendReport($report);
                }

                return self::FAILURE;
            }

            foreach ($report['candidates'] as $row) {
                if ($audit->ban($row)) {
                    $banned++;
                    $this->line('  banned #' . $row['id'] . ' ' . $row['name'] . ' (' . $row['idle_days'] . 'd)');
                }
            }
            $this->info($banned . ' account(s) blocked.');

            // Re-scan so the email reflects reality rather than the pre-ban snapshot.
            if ($banned) {
                $report = $audit->scan();
            }
        } else {
            $this->comment('No account was blocked (report-only or dry-run).');
        }

        if (!$this->option('no-mail')) {
            $this->sendReport($report);
        }

        InactivityConfig::put('inactivity_last_run_at', date('Y-m-d H:i:s'));

        return self::SUCCESS;
    }

    private function sendReport(array $report): void
    {
        $recipients = InactivityConfig::reportEmails();

        if (!$recipients) {
            $this->warn('No report recipients configured — skipping email.');
            return;
        }

        try {
            $from = InactivityConfig::applyMailConfig();
            Mail::to($recipients)->send(new InactivityReportMail($report, $from));
            $this->info('Report emailed to: ' . implode(', ', $recipients));
        } catch (\Throwable $e) {
            // A dead mailbox must never abort the sweep — the bans and the report page
            // are the durable output; the email is a convenience.
            $this->error('Email failed: ' . $e->getMessage());
            \Log::error('inactivity report email failed', ['error' => $e->getMessage()]);
        }
    }
}
