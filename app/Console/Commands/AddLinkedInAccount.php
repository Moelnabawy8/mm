<?php

namespace App\Console\Commands;

use App\Models\MarketingAccount;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AddLinkedInAccount extends Command
{
    protected $signature = 'multipost:add-linkedin
                            {--token= : LinkedIn OAuth2 access token}
                            {--urn= : Author URN (e.g. urn:li:organization:71610229 or urn:li:company:71610229)}
                            {--name= : Display name for the account (optional)}
                            {--user-id= : Owner user ID (defaults to first admin)}
                            {--test : Send a test post after adding}';

    protected $description = 'Add a LinkedIn organization/company account to the marketing module';

    public function handle(): int
    {
        $token = $this->option('token') ?: $this->ask('LinkedIn Access Token');
        $urn   = $this->option('urn')   ?: $this->ask('Author URN (e.g. urn:li:organization:71610229)');

        if (!$token || !$urn) {
            $this->error('Token and URN are required.');
            return self::FAILURE;
        }

        // Normalize URN — convert legacy "company" to "organization"
        $normalizedUrn = $urn;
        if (!str_starts_with($normalizedUrn, 'urn:li:')) {
            $normalizedUrn = 'urn:li:organization:' . $normalizedUrn;
        }
        $normalizedUrn = str_replace('urn:li:company:', 'urn:li:organization:', $normalizedUrn);

        $this->line("Normalized URN: <fg=cyan>{$normalizedUrn}</>");

        // Extract organization ID from URN
        preg_match('/urn:li:(?:organization|person):(\d+)/', $normalizedUrn, $matches);
        $orgId = $matches[1] ?? null;

        if (!$orgId) {
            $this->error("Could not extract organization ID from URN: {$normalizedUrn}");
            return self::FAILURE;
        }

        // ── Verify the token by fetching org details (using stable v2 API) ──
        $this->line('');
        $this->line('🔍 Verifying access token...');

        $verifyResponse = Http::withToken($token)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->get("https://api.linkedin.com/v2/organizations/{$orgId}");

        if (!$verifyResponse->successful()) {
            $this->warn("⚠️  Could not fetch org details (status: {$verifyResponse->status()})");
            $this->line("Response: " . $verifyResponse->body());

            if (!$this->confirm('Continue adding the account anyway?', true)) {
                return self::FAILURE;
            }

            $accountName = $this->option('name') ?: $this->ask('Account display name', "LinkedIn Org #{$orgId}");
        } else {
            $orgData = $verifyResponse->json();
            $orgName = $orgData['localizedName']
                ?? $orgData['name']['localized']['en_US']
                ?? "LinkedIn Org #{$orgId}";

            $this->info("✅ Token valid! Organization: {$orgName}");
            $accountName = $this->option('name') ?: $orgName;
        }

        // ── Find owner user ──
        $userId = $this->option('user-id');
        if (!$userId) {
            $admin = User::where('type', 'super admin')->first()
                ?? User::where('type', 'admin')->first()
                ?? User::first();

            if (!$admin) {
                $this->error('No user found in the system. Pass --user-id=X explicitly.');
                return self::FAILURE;
            }

            $userId = $admin->id;
            $this->line("Owner: <fg=cyan>{$admin->email}</> (ID: {$userId})");
        }

        // ── Check for duplicate ──
        $existing = MarketingAccount::where('platform', 'linkedin')
            ->where('account_id', $normalizedUrn)
            ->first();

        if ($existing) {
            $this->warn("⚠️  Account already exists (ID: {$existing->id}). Updating token...");
            $existing->update([
                'access_token' => $token,
                'status'       => 'active',
            ]);
            $account = $existing;
        } else {
            $account = MarketingAccount::create([
                'created_by'    => $userId,
                'platform'      => 'linkedin',
                'account_name'  => $accountName,
                'account_id'    => $normalizedUrn,
                'access_token'  => $token,
                'page_id'       => $orgId,
                'page_name'     => $accountName,
                'status'        => 'active',
                'metadata'      => [
                    'urn_type'      => 'organization',
                    'org_id'        => $orgId,
                    'original_urn'  => $urn,
                    'added_via'     => 'cli_command',
                ],
            ]);

            $this->info("");
            $this->info("✅ LinkedIn account added successfully!");
        }

        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['ID',          $account->id],
                ['Platform',    $account->platform],
                ['Name',        $account->account_name],
                ['URN',         $account->account_id],
                ['Org ID',      $orgId],
                ['Status',      $account->status],
                ['Owner',       $account->created_by],
            ]
        );

        // ── Optional test post ──
        if ($this->option('test') || $this->confirm("\nSend a test post to LinkedIn now?", false)) {
            $this->testPost($account);
        }

        $this->newLine();
        $this->info("Now you can use Multi-Post at: " . url('/marketing/multi-post'));

        return self::SUCCESS;
    }

    private function testPost(MarketingAccount $account): void
    {
        $this->line('');
        $this->line('📤 Sending test post via /v2/ugcPosts (stable endpoint)...');

        $testContent = "🚀 Test post from ERP system at " . now()->format('Y-m-d H:i:s')
            . "\n\nThis is an automated test of the multi-post integration.";

        $postData = [
            'author'         => $account->account_id,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $testContent,
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = Http::withToken($account->access_token)
            ->withHeaders([
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type'              => 'application/json',
            ])
            ->post('https://api.linkedin.com/v2/ugcPosts', $postData);

        if ($response->successful() || $response->status() === 201) {
            $postUrn = $response->header('x-restli-id') ?? $response->json('id') ?? 'unknown';
            $this->info("✅ Test post published! Post URN: {$postUrn}");
            $this->line("   View on LinkedIn: https://www.linkedin.com/feed/update/{$postUrn}/");
        } else {
            $this->error("❌ Test post failed (HTTP {$response->status()})");
            $this->line("Response: " . $response->body());

            // Common error hints
            $body = $response->body();
            if (str_contains($body, 'permission') || str_contains($body, 'scope') || str_contains($body, 'ACCESS_DENIED')) {
                $this->warn("💡 Hint: The token may be missing required scope: w_organization_social");
                $this->warn("    For company posts you need an admin role + 'Marketing Developer Platform' product approval.");
            }
            if (str_contains($body, 'expired') || str_contains($body, 'INVALID_ACCESS_TOKEN') || str_contains($body, 'REVOKED_ACCESS_TOKEN')) {
                $this->warn("💡 Hint: The access token has expired or been revoked — generate a new one.");
            }
        }
    }
}
