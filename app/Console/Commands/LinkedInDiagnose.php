<?php

namespace App\Console\Commands;

use App\Models\MarketingAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LinkedInDiagnose extends Command
{
    protected $signature = 'multipost:linkedin-diagnose
                            {--token= : Access token (or use --account-id to load from DB)}
                            {--account-id= : Load token from existing MarketingAccount ID}
                            {--test-person : Try posting as the detected person URN}
                            {--test-org= : Try posting as a specific org URN (e.g. urn:li:organization:71610229)}';

    protected $description = 'Diagnose what a LinkedIn access token can actually do';

    public function handle(): int
    {
        $token = $this->resolveToken();
        if (!$token) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('🔍 LinkedIn Token Diagnostics');
        $this->line(str_repeat('─', 60));

        // ── 1. Identity (OpenID Connect) ──
        $this->newLine();
        $this->line('<fg=cyan>1️⃣  Token Identity (/v2/userinfo)</>');
        $personUrn = null;

        $userInfo = Http::withToken($token)->get('https://api.linkedin.com/v2/userinfo');
        if ($userInfo->successful()) {
            $sub = $userInfo->json('sub');
            $name = $userInfo->json('name') ?? $userInfo->json('given_name');
            $personUrn = 'urn:li:person:' . $sub;
            $this->line("   ✅ Token belongs to: <fg=green>{$name}</>");
            $this->line("   ✅ Person URN: <fg=green>{$personUrn}</>");
            $this->line("   ✅ Has scopes: <fg=green>openid, profile</>");
        } else {
            $this->line("   ⚠️  /userinfo failed (HTTP {$userInfo->status()}) — token may not have 'openid' scope");

            // Try the legacy /me endpoint
            $me = Http::withToken($token)
                ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
                ->get('https://api.linkedin.com/v2/me');

            if ($me->successful()) {
                $personUrn = 'urn:li:person:' . $me->json('id');
                $this->line("   ✅ Found via /v2/me: <fg=green>{$personUrn}</>");
                $this->line("   ✅ Has scopes: <fg=green>r_liteprofile</>");
            } else {
                $this->line("   ❌ /v2/me also failed (HTTP {$me->status()})");
                $this->line("   Response: " . $me->body());
            }
        }

        // ── 2. Organization Access ──
        $this->newLine();
        $this->line('<fg=cyan>2️⃣  Organizations You Can Manage (/v2/organizationAcls)</>');

        $orgs = Http::withToken($token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->get('https://api.linkedin.com/v2/organizationAcls', [
                'q'     => 'roleAssignee',
                'role'  => 'ADMINISTRATOR',
                'state' => 'APPROVED',
            ]);

        $manageableOrgs = [];
        if ($orgs->successful()) {
            $elements = $orgs->json('elements') ?? [];
            if (empty($elements)) {
                $this->line("   ⚠️  No organizations found where this token is ADMINISTRATOR");
                $this->line("   This means you cannot post as a company page with this token.");
            } else {
                $this->line("   ✅ Found " . count($elements) . " organization(s):");
                foreach ($elements as $el) {
                    $orgUrn = $el['organization'] ?? 'unknown';
                    $role   = $el['role'] ?? 'unknown';
                    $state  = $el['state'] ?? 'unknown';
                    $this->line("      • <fg=green>{$orgUrn}</> (role: {$role}, state: {$state})");
                    $manageableOrgs[] = $orgUrn;
                }
            }
        } else {
            $this->line("   ❌ Cannot fetch org ACLs (HTTP {$orgs->status()})");
            $this->line("   Response: " . substr($orgs->body(), 0, 200));
            $this->line("   💡 Token is likely missing scope: <fg=yellow>rw_organization_admin</> or <fg=yellow>r_organization_admin</>");
        }

        // ── 3. Test Posting Capability ──
        $this->newLine();
        $this->line('<fg=cyan>3️⃣  Posting Capability Test</>');

        $testOrgUrn = $this->option('test-org');
        if ($testOrgUrn || $this->option('test-person')) {
            if ($this->option('test-person') && $personUrn) {
                $this->testPost($token, $personUrn, 'PERSON');
            }
            if ($testOrgUrn) {
                $this->testPost($token, $testOrgUrn, 'ORGANIZATION');
            }
        } else {
            $this->line("   (Skipped — pass --test-person or --test-org=urn:li:organization:XXX to test)");
        }

        // ── 4. Recommendations ──
        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->components->info('📋 Recommendations');

        if ($personUrn && empty($manageableOrgs)) {
            $this->newLine();
            $this->line("Your token can only post as a <fg=yellow>PERSON</>, not as a company page.");
            $this->newLine();
            $this->line("<fg=cyan>Option A:</> Update your existing LinkedIn account to post as person:");
            $this->line("   <fg=green>php artisan tinker</fg=green>");
            $this->line("   <fg=green>>>> \\App\\Models\\MarketingAccount::find(46)->update(['account_id' => '{$personUrn}']);</fg=green>");
            $this->newLine();
            $this->line("<fg=cyan>Option B:</> Get a token with company-posting permissions:");
            $this->line("   1. Your LinkedIn app needs <fg=yellow>'Community Management API'</> product (requires approval)");
            $this->line("   2. Re-auth with scopes: <fg=yellow>w_organization_social r_organization_admin</>");
            $this->line("   3. The user authorizing must be ADMIN of the company page");
        } elseif (!empty($manageableOrgs)) {
            $this->newLine();
            $this->line("✅ Your token CAN post as the following org(s):");
            foreach ($manageableOrgs as $org) {
                $this->line("   • <fg=green>{$org}</>");
            }
            $this->newLine();
            $userOrg = 'urn:li:organization:71610229';
            if (in_array($userOrg, $manageableOrgs)) {
                $this->line("Your target org ({$userOrg}) <fg=green>IS</> in the list — posting should work!");
                $this->line("If it still fails, the token may be missing <fg=yellow>w_organization_social</> scope.");
            } else {
                $this->line("⚠️  Your target org (urn:li:organization:71610229) is NOT in the manageable list.");
                $this->line("Update account 46 to use one of the URNs above:");
                $this->line("   <fg=green>php artisan tinker</fg=green>");
                $this->line("   <fg=green>>>> \\App\\Models\\MarketingAccount::find(46)->update(['account_id' => '" . $manageableOrgs[0] . "']);</fg=green>");
            }
        } else {
            $this->newLine();
            $this->line("❌ Token has no useful permissions. Generate a new token with these scopes:");
            $this->line("   For personal posts:  <fg=yellow>openid profile w_member_social</>");
            $this->line("   For company posts:   <fg=yellow>w_organization_social r_organization_admin</>");
        }

        $this->newLine();
        return self::SUCCESS;
    }

    private function resolveToken(): ?string
    {
        if ($accountId = $this->option('account-id')) {
            $account = MarketingAccount::find($accountId);
            if (!$account) {
                $this->error("Account ID {$accountId} not found");
                return null;
            }
            $this->line("Loaded token from account [{$account->account_name}] (ID: {$accountId})");
            return $account->access_token;
        }

        return $this->option('token') ?: $this->ask('LinkedIn Access Token');
    }

    private function testPost(string $token, string $authorUrn, string $type): void
    {
        $this->newLine();
        $this->line("   <fg=cyan>Testing post as {$type}: {$authorUrn}</>");

        $postData = [
            'author'         => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => "Test post from ERP at " . now()->format('H:i:s')],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = Http::withToken($token)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type'              => 'application/json',
            ])
            ->post('https://api.linkedin.com/v2/ugcPosts', $postData);

        if ($response->successful() || $response->status() === 201) {
            $postUrn = $response->header('x-restli-id') ?? $response->json('id');
            $this->line("   ✅ <fg=green>SUCCESS!</> Post URN: {$postUrn}");
        } else {
            $this->line("   ❌ Failed (HTTP {$response->status()})");
            $this->line("   Error: " . substr($response->body(), 0, 300));
        }
    }
}
