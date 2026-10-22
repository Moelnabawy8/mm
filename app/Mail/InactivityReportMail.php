<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InactivityReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $report;
    public string $fromAddress;

    public function __construct(array $report, string $fromAddress)
    {
        $this->report      = $report;
        $this->fromAddress = $fromAddress;
    }

    public function build()
    {
        $subject = __('Weekly inactivity report')
            . ' — ' . date('Y-m-d')
            . ' (' . count($this->report['candidates']) . ' ' . __('inactive') . ')';

        return $this->from($this->fromAddress, config('mail.from.name'))
            ->subject($subject)
            ->view('emails.inactivity_report')
            ->with(['report' => $this->report]);
    }
}
