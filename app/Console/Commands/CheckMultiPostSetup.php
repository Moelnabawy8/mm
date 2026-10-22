<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckMultiPostSetup extends Command
{
    protected $signature = 'multipost:check
                            {--fix : Attempt to auto-fix issues where possible}
                            {--test-telegram : Send a test message to a Telegram account}
                            {--account-id= : Specific account ID to test}';

    protected $description = 'Check that all Multi-Post feature components are properly set up';

    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('🔍 Multi-Post Feature — Full System Check');
        $this->line(str_repeat('─', 60));
        $this->newLine();

        // ── 1. Database ──
        $this->components->info('1️⃣  Database Checks');
        $this->checkTable('multi_post_logs', [
            'id', 'batch_id', 'created_by', 'account_id', 'post_id',
            'platform', 'status', 'remote_post_id', 'error_message', 'published_at',
        ]);
        $this->checkTable('marketing_accounts', [
            'id', 'created_by', 'platform', 'account_name', 'access_token',
            'metadata', 'status',
        ]);
        $this->checkTable('marketing_posts', [
            'id', 'created_by', 'account_id', 'post_id', 'content',
            'media_url', 'media_type', 'status',
        ]);
        $this->newLine();

        // ── 2. Files ──
        $this->components->info('2️⃣  Required Files');
        $files = [
            'app/Models/MultiPostLog.php'                         => 'MultiPostLog Model',
            'app/Services/TelegramService.php'                    => 'Telegram Service',
            'app/Jobs/PublishToSocialPlatform.php'                 => 'Publish Job',
            'app/Http/Controllers/MarketingController.php'        => 'Marketing Controller',
            'app/Models/MarketingAccount.php'                     => 'MarketingAccount Model',
            'app/Models/MarketingPost.php'                        => 'MarketingPost Model',
            'app/Services/MetaApiService.php'                     => 'Meta API Service',
            'resources/views/marketing/posts/multi.blade.php'     => 'Multi-Post Form View',
            'resources/views/marketing/posts/multi_status.blade.php' => 'Multi-Post Status View',
        ];

        foreach ($files as $path => $label) {
            $fullPath = base_path($path);
            if (file_exists($fullPath)) {
                $this->pass("{$label} — {$path}");
            } else {
                $this->failCheck("{$label} — {$path} NOT FOUND");
            }
        }
        $this->newLine();

        // ── 3. Class Loading ──
        $this->components->info('3️⃣  Class Autoloading');
        $classes = [
            \App\Models\MultiPostLog::class,
            \App\Models\MarketingAccount::class,
            \App\Models\MarketingPost::class,
            \App\Services\TelegramService::class,
            \App\Services\MetaApiService::class,
            \App\Jobs\PublishToSocialPlatform::class,
        ];

        foreach ($classes as $class) {
            if (class_exists($class)) {
                $this->pass("Class loadable: {$class}");
            } else {
                $this->failCheck("Class NOT loadable: {$class}");
            }
        }
        $this->newLine();

        // ── 4. Routes ──
        $this->components->info('4️⃣  Routes');
        $routes = [
            'marketing.multipost'             => 'GET  /marketing/multi-post',
            'marketing.multipost.store'       => 'POST /marketing/multi-post',
            'marketing.multipost.status'      => 'GET  /marketing/multi-post/{batchId}/status',
            'marketing.multipost.status.json' => 'GET  /marketing/multi-post/{batchId}/status-json',
        ];

        $router = app('router');
        foreach ($routes as $name => $description) {
            if ($router->has($name)) {
                $this->pass("Route [{$name}] — {$description}");
            } else {
                $this->failCheck("Route [{$name}] NOT registered — {$description}");
            }
        }
        $this->newLine();

        // ── 5. Controller Methods ──
        $this->components->info('5️⃣  Controller Methods');
        $methods = ['multiPost', 'storeMultiPost', 'multiPostStatus', 'multiPostStatusJson'];
        $reflection = new \ReflectionClass(\App\Http\Controllers\MarketingController::class);

        foreach ($methods as $method) {
            if ($reflection->hasMethod($method)) {
                $this->pass("MarketingController::{$method}()");
            } else {
                $this->failCheck("MarketingController::{$method}() — MISSING");
            }
        }
        $this->newLine();

        // ── 6. Queue Config ──
        $this->components->info('6️⃣  Queue Configuration');
        $queueDriver = config('queue.default');
        if ($queueDriver === 'sync') {
            $this->warnCheck("Queue driver is 'sync' — jobs run synchronously (slower for multi-post)");
            $this->line('   💡 For production, consider switching to "database" or "redis":');
            $this->line('      1. Set QUEUE_CONNECTION=database in .env');
            $this->line('      2. Run: php artisan queue:table && php artisan migrate');
            $this->line('      3. Run: php artisan queue:work');
        } else {
            $this->pass("Queue driver: {$queueDriver}");

            if ($queueDriver === 'database') {
                if (Schema::hasTable('jobs')) {
                    $this->pass("Jobs table exists");
                    $pendingJobs = DB::table('jobs')->count();
                    $failedJobs = DB::table('failed_jobs')->count();
                    $this->line("   📊 Pending jobs: {$pendingJobs} | Failed jobs: {$failedJobs}");
                } else {
                    $this->failCheck("Jobs table missing — run: php artisan queue:table && php artisan migrate");
                }
            }
        }
        $this->newLine();

        // ── 7. Connected Accounts ──
        $this->components->info('7️⃣  Connected Social Accounts');
        if (Schema::hasTable('marketing_accounts')) {
            $accounts = \App\Models\MarketingAccount::select('platform', 'status', DB::raw('count(*) as total'))
                ->groupBy('platform', 'status')
                ->get();

            if ($accounts->isEmpty()) {
                $this->warn("No social accounts connected yet");
            } else {
                $table = [];
                foreach ($accounts as $row) {
                    $statusIcon = match ($row->status) {
                        'active'   => '🟢',
                        'inactive' => '🟡',
                        'expired'  => '🔴',
                        default    => '⚪',
                    };
                    $table[] = [
                        ucfirst($row->platform),
                        "{$statusIcon} {$row->status}",
                        $row->total,
                    ];
                }
                $this->table(['Platform', 'Status', 'Count'], $table);
            }

            // Check Telegram-specific config
            $telegramAccounts = \App\Models\MarketingAccount::where('platform', 'telegram')
                ->where('status', 'active')
                ->get();

            if ($telegramAccounts->isNotEmpty()) {
                $this->newLine();
                $this->line('   📱 Telegram Accounts:');
                foreach ($telegramAccounts as $tg) {
                    $meta = $tg->metadata ?? [];
                    $hasBot     = !empty($meta['bot_token']);
                    $hasChannel = !empty($meta['channel_id']);

                    if ($hasBot && $hasChannel) {
                        $this->pass("  [{$tg->account_name}] — bot_token ✓, channel_id ✓");
                    } else {
                        $missing = [];
                        if (!$hasBot) $missing[] = 'bot_token';
                        if (!$hasChannel) $missing[] = 'channel_id';
                        $this->failCheck("  [{$tg->account_name}] — missing: " . implode(', ', $missing));
                        $this->line("     Fix: Update metadata JSON → {\"bot_token\": \"...\", \"channel_id\": \"@channel\"}");
                    }
                }
            }

            // Check for Twitter config
            $twitterConfig = config('services.twitter');
            $hasTwitterAccounts = \App\Models\MarketingAccount::whereIn('platform', ['twitter', 'x'])
                ->where('status', 'active')
                ->exists();

            if ($hasTwitterAccounts) {
                $this->newLine();
                $this->line('   🐦 X/Twitter Config:');
                if (!empty($twitterConfig['client_id']) && !empty($twitterConfig['client_secret'])) {
                    $this->pass("  services.twitter.client_id & client_secret configured");
                } else {
                    $this->warnCheck("  services.twitter not configured in config/services.php");
                    $this->line("     Add to .env: TWITTER_CLIENT_ID=xxx, TWITTER_CLIENT_SECRET=xxx");
                }
            }
        }
        $this->newLine();

        // ── 8. Telegram Bot Verification (optional) ──
        if ($this->option('test-telegram')) {
            $this->components->info('8️⃣  Telegram Bot Test');
            $this->testTelegram();
            $this->newLine();
        }

        // ── 9. Recent Multi-Post Activity ──
        $this->components->info('📊 Recent Multi-Post Activity');
        if (Schema::hasTable('multi_post_logs')) {
            $recentBatches = DB::table('multi_post_logs')
                ->select('batch_id', DB::raw('count(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count"),
                    DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                    DB::raw('MAX(created_at) as last_activity'))
                ->groupBy('batch_id')
                ->orderByDesc('last_activity')
                ->limit(5)
                ->get();

            if ($recentBatches->isEmpty()) {
                $this->line('   No multi-post activity yet.');
            } else {
                $table = [];
                foreach ($recentBatches as $batch) {
                    $table[] = [
                        substr($batch->batch_id, 0, 8) . '...',
                        $batch->total,
                        $batch->success_count,
                        $batch->failed_count,
                        $batch->last_activity,
                    ];
                }
                $this->table(['Batch ID', 'Total', 'Success', 'Failed', 'Date'], $table);
            }
        } else {
            $this->warn("multi_post_logs table doesn't exist — run migration first");
        }

        // ── Summary ──
        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->newLine();

        $total = $this->passed + $this->failed + $this->warnings;
        $this->components->info("Results: ✅ {$this->passed} passed  |  ❌ {$this->failed} failed  |  ⚠️  {$this->warnings} warnings");

        if ($this->failed === 0) {
            $this->newLine();
            $this->components->info('🎉 All checks passed! Multi-Post feature is ready.');
            $this->line('   URL: ' . url('/marketing/multi-post'));
        } else {
            $this->newLine();
            $this->components->error("⛔ {$this->failed} issue(s) need to be fixed before the feature works correctly.");
        }

        $this->newLine();

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function testTelegram(): void
    {
        $accountId = $this->option('account-id');

        $query = \App\Models\MarketingAccount::where('platform', 'telegram')->where('status', 'active');
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $account = $query->first();

        if (!$account) {
            $this->failCheck("No active Telegram account found" . ($accountId ? " with ID {$accountId}" : ""));
            return;
        }

        $meta = $account->metadata ?? [];
        $botToken  = $meta['bot_token'] ?? null;
        $channelId = $meta['channel_id'] ?? null;

        if (!$botToken || !$channelId) {
            $this->failCheck("Telegram account [{$account->account_name}] is missing bot_token or channel_id");
            return;
        }

        $this->line("   Testing bot token...");
        $telegram = app(\App\Services\TelegramService::class);

        // Verify the bot
        $verify = $telegram->verifyBot($botToken);
        if ($verify['valid']) {
            $this->pass("Bot verified: {$verify['bot_name']}");
        } else {
            $this->failCheck("Bot verification failed: " . ($verify['error'] ?? 'unknown'));
            return;
        }

        // Send a test message
        if ($this->confirm('Send a test message to channel ' . $channelId . '?', true)) {
            $testMessage = "✅ <b>Multi-Post Test</b>\n\nThis is a test message from your ERP system.\nTime: " . now()->format('Y-m-d H:i:s');
            $result = $telegram->sendMessage($botToken, $channelId, $testMessage);

            if ($result['success']) {
                $this->pass("Test message sent! Message ID: {$result['message_id']}");
            } else {
                $this->failCheck("Failed to send test message: " . ($result['error'] ?? 'unknown'));
            }
        }
    }

    private function checkTable(string $table, array $requiredColumns): void
    {
        if (!Schema::hasTable($table)) {
            $this->failCheck("Table [{$table}] does not exist — run: php artisan migrate");
            return;
        }

        $this->pass("Table [{$table}] exists");

        $missing = [];
        foreach ($requiredColumns as $col) {
            if (!Schema::hasColumn($table, $col)) {
                $missing[] = $col;
            }
        }

        if (!empty($missing)) {
            $this->failCheck("Table [{$table}] missing columns: " . implode(', ', $missing));
        }
    }

    private function pass(string $message): void
    {
        $this->passed++;
        $this->line("   <fg=green>✅ {$message}</>");
    }

    private function failCheck(string $message): void
    {
        $this->failed++;
        $this->line("   <fg=red>❌ {$message}</>");
    }

    private function warnCheck(string $message): void
    {
        $this->warnings++;
        $this->line("   <fg=yellow>⚠️  {$message}</>");
    }
}
