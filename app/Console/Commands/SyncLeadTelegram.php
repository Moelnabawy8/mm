<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;

class SyncLeadTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:sync-telegram';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs existing Lead phone numbers to Telegram field if Telegram is empty';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Telegram sync...');

        $leads = Lead::whereNotNull('phone')
            ->where(function ($query) {
                $query->whereNull('telegram')
                      ->orWhere('telegram', '');
            })
            ->get();

        $count = 0;
        foreach ($leads as $lead) {
            $lead->telegram = $lead->phone;
            $lead->save();
            $count++;
        }

        $this->info("Synced {$count} leads.");

        return 0;
    }
}
