<?php
/**
 * Test: Page Insights Sync (Facebook + Instagram)
 * Tests: getPageInsightsSummary, getInstagramInsights, syncAccountInsights
 */

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MetaApiService;
use App\Models\MarketingAccount;
use App\Models\MarketingInsight;

function h($t) { echo "\n" . str_repeat('=', 60) . "\n  {$t}\n" . str_repeat('=', 60) . "\n"; }
function ok($l, $r = true) { echo ($r ? "  ✅" : "  ❌") . " {$l}\n"; return (bool)$r; }
function info($l) { echo "     {$l}\n"; }

$metaApi = new MetaApiService();
$results = [];

// ═══════════════════════════════════════════════
// SETUP: Find accounts
// ═══════════════════════════════════════════════
h('1. SETUP — Finding Accounts');

$fbAccount = MarketingAccount::where('platform', 'facebook')
    ->where('status', 'active')
    ->whereNotNull('access_token')
    ->whereNotNull('page_id')
    ->first();

$igAccount = MarketingAccount::where('platform', 'instagram')
    ->where('status', 'active')
    ->whereNotNull('access_token')
    ->whereNotNull('page_id')
    ->first();

$results['Facebook account found'] = ok("Facebook: " . ($fbAccount?->account_name ?? 'NOT FOUND'), (bool)$fbAccount);
$results['Instagram account found'] = ok("Instagram: " . ($igAccount?->account_name ?? 'NOT FOUND'), (bool)$igAccount);

// ═══════════════════════════════════════════════
// TEST 1: Facebook getPageInsightsSummary
// ═══════════════════════════════════════════════
h('2. FACEBOOK — getPageInsightsSummary()');

if ($fbAccount) {
    echo "  Page: {$fbAccount->account_name} (ID: {$fbAccount->page_id})\n";

    $fbData = $metaApi->getPageInsightsSummary($fbAccount->page_id, $fbAccount->access_token);

    if ($fbData && is_array($fbData)) {
        info("Followers:   " . number_format($fbData['followers']));
        info("Reach:       " . number_format($fbData['reach']));
        info("Impressions: " . number_format($fbData['impressions']));
        info("Page Views:  " . number_format($fbData['page_views']));
        info("Video Views: " . number_format($fbData['video_views']));
        info("Engagement:  " . number_format($fbData['engagement']));

        $results['FB getPageInsightsSummary'] = ok("Facebook insights fetched", true);
        $results['FB has followers'] = ok("Followers > 0: {$fbData['followers']}", $fbData['followers'] > 0);
    } else {
        $results['FB getPageInsightsSummary'] = ok("Facebook insights FAILED", false);
        $results['FB has followers'] = ok("Skipped", false);
    }
} else {
    $results['FB getPageInsightsSummary'] = ok("Skipped — no Facebook account", false);
    $results['FB has followers'] = ok("Skipped", false);
}

// ═══════════════════════════════════════════════
// TEST 2: Instagram getInstagramInsights
// ═══════════════════════════════════════════════
h('3. INSTAGRAM — getInstagramInsights()');

if ($igAccount) {
    echo "  Account: {$igAccount->account_name} (ID: {$igAccount->page_id})\n";

    $igData = $metaApi->getInstagramInsights($igAccount->page_id, $igAccount->access_token);

    if ($igData && is_array($igData)) {
        info("Followers:     " . number_format($igData['followers']));
        info("Reach:         " . number_format($igData['reach']));
        info("Impressions:   " . number_format($igData['impressions']));
        info("Profile Views: " . number_format($igData['profile_views']));

        $results['IG getInstagramInsights'] = ok("Instagram insights fetched", true);
        $results['IG has followers'] = ok("Followers > 0: {$igData['followers']}", $igData['followers'] > 0);
    } else {
        $results['IG getInstagramInsights'] = ok("Instagram insights FAILED", false);
        $results['IG has followers'] = ok("Skipped", false);
    }
} else {
    $results['IG getInstagramInsights'] = ok("Skipped — no Instagram account", false);
    $results['IG has followers'] = ok("Skipped", false);
}

// ═══════════════════════════════════════════════
// TEST 3: Save to DB (syncAccountInsights logic)
// ═══════════════════════════════════════════════
h('4. SAVE TO DATABASE — MarketingInsight');

if ($fbAccount && isset($fbData) && $fbData) {
    $insight = MarketingInsight::updateOrCreate(
        ['account_id' => $fbAccount->id, 'date' => now()->toDateString()],
        [
            'followers'        => $fbData['followers'] ?? 0,
            'page_views'       => $fbData['page_views'] ?? 0,
            'profile_visits'   => $fbData['page_views'] ?? 0,
            'total_engagement' => $fbData['engagement'] ?? 0,
            'engagement_rate'  => ($fbData['followers'] > 0 && $fbData['engagement'] > 0)
                ? round(($fbData['engagement'] / $fbData['followers']) * 100, 4)
                : 0,
            'metadata' => [
                'reach'       => $fbData['reach'] ?? 0,
                'impressions' => $fbData['impressions'] ?? 0,
                'video_views' => $fbData['video_views'] ?? 0,
            ],
        ]
    );

    info("Saved ID: {$insight->id} | Date: {$insight->date}");
    info("Reach from metadata: " . ($insight->metadata['reach'] ?? 0));
    $results['FB saved to DB'] = ok("Facebook insight saved to marketing_insights");
} else {
    $results['FB saved to DB'] = ok("Skipped — no Facebook data", false);
}

if ($igAccount && isset($igData) && $igData) {
    $insight = MarketingInsight::updateOrCreate(
        ['account_id' => $igAccount->id, 'date' => now()->toDateString()],
        [
            'followers'        => $igData['followers'] ?? 0,
            'page_views'       => $igData['profile_views'] ?? 0,
            'profile_visits'   => $igData['profile_views'] ?? 0,
            'total_engagement' => 0,
            'engagement_rate'  => 0,
            'metadata' => [
                'reach'       => $igData['reach'] ?? 0,
                'impressions' => $igData['impressions'] ?? 0,
                'video_views' => 0,
            ],
        ]
    );

    info("Saved ID: {$insight->id} | Date: {$insight->date}");
    info("Reach from metadata: " . ($insight->metadata['reach'] ?? 0));
    $results['IG saved to DB'] = ok("Instagram insight saved to marketing_insights");
} else {
    $results['IG saved to DB'] = ok("Skipped — no Instagram data", false);
}

// ═══════════════════════════════════════════════
// TEST 4: Read back from DB
// ═══════════════════════════════════════════════
h('5. READ BACK FROM DATABASE');

$allInsights = MarketingInsight::whereDate('date', now()->toDateString())->with('account')->get();

if ($allInsights->isNotEmpty()) {
    foreach ($allInsights as $ins) {
        echo "\n  📊 {$ins->account->account_name} ({$ins->account->platform})\n";
        info("Followers:   " . number_format($ins->followers));
        info("Reach:       " . number_format($ins->metadata['reach'] ?? 0));
        info("Impressions: " . number_format($ins->metadata['impressions'] ?? 0));
        info("Video Views: " . number_format($ins->metadata['video_views'] ?? 0));
        info("Engagement:  " . number_format($ins->total_engagement));
        info("Eng. Rate:   " . number_format($ins->engagement_rate, 2) . "%");
    }
    $results['Read from DB'] = ok("Data readable from DB", true);
} else {
    $results['Read from DB'] = ok("No insights found for today", false);
}

// ═══════════════════════════════════════════════
// TEST 5: Reels stats fix (no shares field)
// ═══════════════════════════════════════════════
h('6. REELS STATS FIX — No shares field');

if ($fbAccount) {
    // Find a published post to test
    $post = \App\Models\MarketingPost::where('account_id', $fbAccount->id)
        ->where('status', 'published')
        ->whereNotNull('post_id')
        ->latest()
        ->first();

    if ($post) {
        echo "  Testing getPostStats on: {$post->post_id}\n";
        $stats = $metaApi->getPostStats($post->post_id, $fbAccount->access_token);

        if ($stats !== false) {
            info("Likes:    {$stats['likes']}");
            info("Comments: {$stats['comments']}");
            info("Shares:   {$stats['shares']}");
            $results['Post stats (Reels-safe)'] = ok("getPostStats works (no crash on Reels)");
        } else {
            $results['Post stats (Reels-safe)'] = ok("getPostStats returned false", false);
        }
    } else {
        $results['Post stats (Reels-safe)'] = ok("Skipped — no published posts found", null);
        echo "  (No published posts to test)\n";
    }
} else {
    $results['Post stats (Reels-safe)'] = ok("Skipped — no Facebook account", false);
}

// ═══════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════
h('FINAL SUMMARY');

$passed = 0; $failed = 0; $skipped = 0;
foreach ($results as $name => $r) {
    if ($r === null) {
        echo "  ⏭  {$name}\n";
        $skipped++;
    } else {
        echo ($r ? "  ✅" : "  ❌") . " {$name}\n";
        $r ? $passed++ : $failed++;
    }
}

echo "\n  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Results: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
echo "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
