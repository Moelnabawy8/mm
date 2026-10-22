<?php

namespace App\Console\Commands;

use App\Models\MarketingAccount;
use App\Services\MetaApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pre-compute each account's ALL-TIME Meta totals (followers + cumulative
 * reach/engagement/etc. over Meta's ~2-year insights retention) and store them on the
 * account so the "Real-time" view can display the grand totals INSTANTLY.
 *
 * The Graph API has no single "lifetime engagement" number, so we sum it over the max
 * retained window. That is dozens of API calls per page — far too heavy to run on every
 * page load, hence this nightly job that does it once and caches the result in
 * marketing_accounts.metadata['lifetime'].
 *
 * Usage:
 *   php artisan marketing:compute-lifetime-totals
 *   php artisan marketing:compute-lifetime-totals --account=12
 */
class ComputeLifetimeTotals extends Command
{
    protected $signature = 'marketing:compute-lifetime-totals {--account= : Only recompute this account id}';

    protected $description = 'Pre-compute and store all-time Meta totals for the Real-time analytics view';

    public function handle(MetaApiService $meta): int
    {
        $until = time();
        $since = $until - (730 * 86400); // ~2 years = Meta insights retention window

        $query = MarketingAccount::whereIn('platform', ['facebook', 'instagram'])
            ->whereNotNull('access_token')
            ->whereNotNull('page_id');

        if ($this->option('account')) {
            $query->where('id', $this->option('account'));
        }

        $accounts = $query->get();
        $this->info("Computing all-time totals for {$accounts->count()} accounts…");

        $ok = 0;
        $fail = 0;

        // Pace the Graph API so this heavy job doesn't trip rate limits (which would make
        // every metric fall back to a tiny wrong number). ~1.5s between each chunk call.
        $throttleMs = 1500;

        foreach ($accounts as $account) {
            try {
                $stats = $account->platform === 'instagram'
                    ? $meta->getInstagramInsights($account->page_id, $account->access_token, $since, $until, true, $throttleMs, true)
                    : $meta->getPageInsightsSummary($account->page_id, $account->access_token, $since, $until, true, 80, $throttleMs, true);

                if (!is_array($stats) || isset($stats['error'])) {
                    $fail++;
                    $msg = is_array($stats) ? ($stats['error'] ?? 'failed') : 'failed';
                    $this->warn("  ✗ [{$account->platform}] {$account->account_name}: {$msg}");
                    continue;
                }

                $stats['computed_at'] = date('Y-m-d H:i:s', $until);

                $metadata = $account->metadata ?? [];
                $metadata['lifetime'] = $stats;
                $account->metadata = $metadata;
                $account->save();

                $ok++;
                $eng = $stats['engagements'] ?? $stats['interactions'] ?? '-';
                $this->info("  ✓ [{$account->platform}] {$account->account_name}: follows={$stats['follows']}, engagement={$eng}");
            } catch (\Throwable $e) {
                $fail++;
                Log::error("compute-lifetime-totals [{$account->id}]: " . $e->getMessage());
                $this->warn("  ✗ [{$account->platform}] {$account->account_name}: " . $e->getMessage());
            }

            // Be gentle on the Graph API between accounts.
            sleep(3);
        }

        $this->info("Done. stored={$ok}, failed={$fail}");
        Log::info("marketing:compute-lifetime-totals stored={$ok} failed={$fail}");

        return self::SUCCESS;
    }
}
