<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\ConnectionDoctor;
use App\Support\WhatsApp\WhatsAppGate;
use Illuminate\Console\Command;

/**
 * CLI front-end for App\Services\WhatsApp\ConnectionDoctor.
 *
 * All the actual checks live in that service so this command and the "Test connection"
 * button in the settings screen can never disagree about the state of the integration.
 */
class WhatsAppDoctor extends Command
{
    protected $signature = 'whatsapp:doctor {--json : machine-readable output}';

    protected $description = 'Diagnose the WhatsApp task-notification integration end to end';

    public function handle(): int
    {
        WhatsAppGate::allowCli();

        $doctor  = new ConnectionDoctor();
        $results = $doctor->run();
        $summary = $doctor->summary();

        if ($this->option('json')) {
            $this->line(json_encode(
                ['summary' => $summary, 'checks' => $results],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $icons = [
            ConnectionDoctor::PASS => '<fg=green>✔</>',
            ConnectionDoctor::WARN => '<fg=yellow>▲</>',
            ConnectionDoctor::FAIL => '<fg=red>✖</>',
            ConnectionDoctor::INFO => '<fg=blue>•</>',
        ];

        $this->newLine();
        $this->line('  <options=bold>WhatsApp integration diagnostics</>');
        $this->newLine();

        foreach ($results as $r) {
            $this->line(sprintf('  %s  %-26s %s', $icons[$r['level']] ?? '?', $r['name'], $r['detail']));
        }

        $this->newLine();
        if ($summary['failed'] === 0 && $summary['warnings'] === 0) {
            $this->info('  All checks passed.');
        } else {
            $this->line("  <fg=red>{$summary['failed']} failed</>, <fg=yellow>{$summary['warnings']} warnings</>");
        }
        $this->newLine();

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
