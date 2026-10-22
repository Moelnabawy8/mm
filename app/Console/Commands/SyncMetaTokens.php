<?php

namespace App\Console\Commands;

use App\Models\MarketingAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Refresh every Facebook Page / Instagram access token stored in marketing_accounts
 * from a single long-lived (Never-expiring) Facebook USER token.
 *
 * Page access tokens derived from a long-lived user token do not expire on their own,
 * so running this once fixes dead tokens, and running it on a daily schedule guarantees
 * the stored tokens stay valid even if Facebook ever rotates them.
 *
 * Usage:
 *   php artisan marketing:sync-meta-tokens                 (uses META_USER_TOKEN from .env)
 *   php artisan marketing:sync-meta-tokens --token=EAAB... (one-off override)
 */
class SyncMetaTokens extends Command
{
    protected $signature = 'marketing:sync-meta-tokens {--token= : Facebook USER token (defaults to META_USER_TOKEN in .env)}';

    protected $description = 'Refresh stored Facebook/Instagram Page tokens from a long-lived user token';

    private string $baseUrl = 'https://graph.facebook.com/v23.0';

    public function handle(): int
    {
        $userToken = $this->option('token') ?: config('services.meta.user_token');

        if (!$userToken) {
            $this->error('No user token provided. Pass --token=... or set META_USER_TOKEN in .env');
            return self::FAILURE;
        }

        // 1) Confirm the token is alive and who it belongs to.
        $me = Http::get($this->baseUrl . '/me', [
            'fields'       => 'id,name',
            'access_token' => $userToken,
        ]);

        if (!$me->successful()) {
            $err = $me->json()['error']['message'] ?? $me->body();
            $this->error('User token is invalid/expired: ' . $err);
            return self::FAILURE;
        }
        $this->info('Token owner: ' . ($me->json()['name'] ?? '?') . ' (' . ($me->json()['id'] ?? '?') . ')');

        // 2) Pull every Page the user manages (with its Page token + linked IG account).
        $pages   = [];
        $url     = $this->baseUrl . '/me/accounts';
        $params  = [
            'fields'       => 'name,id,access_token,instagram_business_account{id,username}',
            'limit'        => 100,
            'access_token' => $userToken,
        ];

        do {
            $resp = Http::get($url, $params);
            if (!$resp->successful()) {
                $this->error('Failed to list pages: ' . ($resp->json()['error']['message'] ?? $resp->body()));
                return self::FAILURE;
            }
            $json   = $resp->json();
            $pages  = array_merge($pages, $json['data'] ?? []);
            $url    = $json['paging']['next'] ?? null;
            $params = []; // the "next" URL already carries all query params
        } while ($url);

        $this->info('Pages returned by Facebook: ' . count($pages));

        // 3) Update each matching account by page_id (FB) and by IG user id (Instagram).
        $fbUpdated = 0;
        $igUpdated = 0;
        $coveredFb = [];
        $coveredIg = [];
        $rows      = [];

        foreach ($pages as $page) {
            $pageId    = $page['id'] ?? null;
            $pageToken = $page['access_token'] ?? null;
            if (!$pageId || !$pageToken) {
                continue;
            }

            $coveredFb[] = $pageId;

            $n = MarketingAccount::where('platform', 'facebook')
                ->where('page_id', $pageId)
                ->update([
                    'access_token'     => $pageToken,
                    'token_expires_at' => null, // page tokens from a long-lived user token don't expire
                    'status'           => 'connected',
                ]);
            $fbUpdated += $n;
            $rows[] = [$page['name'] ?? '', 'facebook', $pageId, $n ? 'updated' : 'no account row'];

            // Instagram business account linked to this page (if any) — uses the page token.
            $ig = $page['instagram_business_account']['id'] ?? null;
            if ($ig) {
                $coveredIg[] = $ig;
                $m = MarketingAccount::where('platform', 'instagram')
                    ->where('page_id', $ig)
                    ->update([
                        'access_token'     => $pageToken,
                        'token_expires_at' => null,
                        'status'           => 'connected',
                    ]);
                $igUpdated += $m;
                $rows[] = [($page['instagram_business_account']['username'] ?? $ig), 'instagram', $ig, $m ? 'updated' : 'no account row'];
            }
        }

        $this->table(['Name', 'Platform', 'ID', 'Result'], $rows);
        $this->info("Facebook accounts updated: {$fbUpdated}");
        $this->info("Instagram accounts updated: {$igUpdated}");

        // 4) Report accounts this token could NOT cover (belong to a different owner).
        $uncovered = MarketingAccount::whereIn('platform', ['facebook', 'instagram'])
            ->where(function ($q) use ($coveredFb, $coveredIg) {
                $q->where(function ($q2) use ($coveredFb) {
                    $q2->where('platform', 'facebook');
                    if ($coveredFb) $q2->whereNotIn('page_id', $coveredFb);
                })->orWhere(function ($q2) use ($coveredIg) {
                    $q2->where('platform', 'instagram');
                    if ($coveredIg) $q2->whereNotIn('page_id', $coveredIg);
                });
            })
            ->get(['account_name', 'platform', 'page_id']);

        if ($uncovered->isNotEmpty()) {
            $this->warn('These accounts were NOT covered by this token (need their own owner\'s token):');
            foreach ($uncovered as $a) {
                $this->line('  - [' . $a->platform . '] ' . $a->account_name . ' (page_id ' . $a->page_id . ')');
            }
        }

        Log::info("marketing:sync-meta-tokens — fb={$fbUpdated}, ig={$igUpdated}, uncovered={$uncovered->count()}");

        return self::SUCCESS;
    }
}
