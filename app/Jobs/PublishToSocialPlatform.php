<?php

namespace App\Jobs;

use App\Models\MarketingAccount;
use App\Models\MarketingPost;
use App\Models\MultiPostLog;
use App\Services\MetaApiService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishToSocialPlatform implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        protected int $logId,
        protected string $content,
        protected ?string $mediaUrl,
        protected string $mediaType,
        protected ?int $scheduledTimestamp = null,
    ) {}

    public function handle(MetaApiService $metaApi, TelegramService $telegram): void
    {
        $log = MultiPostLog::find($this->logId);
        if (!$log) {
            return;
        }

        $account = MarketingAccount::find($log->account_id);
        if (!$account) {
            $log->update(['status' => 'failed', 'error_message' => 'Account not found']);
            return;
        }

        $log->update(['status' => 'publishing']);

        try {
            $result = match ($account->platform) {
                'facebook'  => $this->publishToFacebook($metaApi, $account),
                'instagram' => $this->publishToInstagram($metaApi, $account),
                'telegram'  => $this->publishToTelegram($telegram, $account),
                'twitter', 'x' => $this->publishToTwitter($account),
                'linkedin'  => $this->publishToLinkedIn($account),
                default     => ['error' => "Unsupported platform: {$account->platform}"],
            };

            if (isset($result['error'])) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => $result['error'],
                ]);
                return;
            }

            $remotePostId = $result['feed_post_id'] ?? $result['post_id'] ?? $result['message_id'] ?? $result['id'] ?? null;

            // Create a MarketingPost record for this platform
            $post = MarketingPost::create([
                'created_by'   => $log->created_by,
                'account_id'   => $account->id,
                'post_id'      => $remotePostId,
                'content'      => $this->content,
                'media_url'    => $this->mediaUrl,
                'media_type'   => $this->mediaType,
                'status'       => $this->scheduledTimestamp ? 'scheduled' : 'published',
                'scheduled_at' => $this->scheduledTimestamp ? date('Y-m-d H:i:s', $this->scheduledTimestamp) : null,
                'published_at' => $this->scheduledTimestamp ? null : now(),
            ]);

            $log->update([
                'status'         => 'success',
                'post_id'        => $post->id,
                'remote_post_id' => $remotePostId,
                'published_at'   => now(),
            ]);

        } catch (\Exception $e) {
            Log::error("PublishToSocialPlatform failed [{$account->platform}]: " . $e->getMessage());
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function publishToFacebook(MetaApiService $metaApi, MarketingAccount $account): array
    {
        return $metaApi->publishPost(
            $account->page_id,
            $account->access_token,
            $this->content,
            $this->mediaUrl,
            $this->scheduledTimestamp,
            $this->mediaType,
        );
    }

    protected function publishToInstagram(MetaApiService $metaApi, MarketingAccount $account): array
    {
        if (!$this->mediaUrl) {
            return ['error' => 'Instagram requires an image or video URL'];
        }

        return $metaApi->publishInstagramPost(
            $account->page_id,
            $account->access_token,
            $this->content,
            $this->mediaUrl,
            $this->mediaType,
            $this->scheduledTimestamp,
        );
    }

    protected function publishToTelegram(TelegramService $telegram, MarketingAccount $account): array
    {
        $botToken  = $account->metadata['bot_token'] ?? null;
        $channelId = $account->metadata['channel_id'] ?? null;

        if (!$botToken || !$channelId) {
            return ['error' => 'Telegram account is missing bot_token or channel_id in metadata'];
        }

        $result = $telegram->publishPost($botToken, $channelId, $this->content, $this->mediaUrl, $this->mediaType);

        if ($result['success']) {
            return ['message_id' => (string) $result['message_id']];
        }

        return ['error' => $result['error'] ?? 'Telegram publish failed'];
    }

    /**
     * X/Twitter posting stub.
     * Recommended package: abraham/twitteroauth (OAuth 1.0a for API v2 post endpoint)
     * Or use Guzzle with OAuth2 Bearer + user-context tokens.
     *
     * API endpoint: POST https://api.twitter.com/2/tweets
     * Required scopes: tweet.write, users.read (OAuth2 PKCE flow)
     */
    protected function publishToTwitter(MarketingAccount $account): array
    {
        $accessToken = $account->access_token;
        if (!$accessToken) {
            return ['error' => 'Twitter/X account has no access token'];
        }

        try {
            $params = ['text' => $this->content];

            // If media is present, upload it first via v1.1 media/upload endpoint
            // then attach the media_id to the tweet
            if ($this->mediaUrl) {
                $mediaId = $this->uploadTwitterMedia($account, $this->mediaUrl);
                if ($mediaId) {
                    $params['media'] = ['media_ids' => [$mediaId]];
                }
            }

            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->post('https://api.twitter.com/2/tweets', $params);

            if ($response->successful()) {
                $data = $response->json('data');
                return ['id' => $data['id'] ?? null];
            }

            return ['error' => $response->json('detail') ?? $response->json('title') ?? 'Twitter API error: ' . $response->status()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function uploadTwitterMedia(MarketingAccount $account, string $mediaUrl): ?string
    {
        try {
            // Download the media file
            $mediaContent = file_get_contents($mediaUrl);
            if (!$mediaContent) {
                return null;
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'tw_media_');
            file_put_contents($tmpFile, $mediaContent);

            // Twitter media upload — uses OAuth 1.0a in production (requires consumer key/secret + token/secret).
            // For now we attempt with the bearer token; for full OAuth1.0a signing, integrate abraham/twitteroauth.
            $response = \Illuminate\Support\Facades\Http::asMultipart()
                ->withToken($account->access_token)
                ->attach('media', file_get_contents($tmpFile), 'media.jpg')
                ->post('https://upload.twitter.com/1.1/media/upload.json');

            unlink($tmpFile);

            if ($response->successful()) {
                return $response->json('media_id_string');
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Twitter media upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * LinkedIn posting using their Marketing API.
     * Recommended: Use LinkedIn's Community Management API (v2).
     *
     * API endpoint: POST https://api.linkedin.com/v2/posts
     * Required scopes: w_member_social (for personal posts) or w_organization_social (for pages)
     * Auth: OAuth2 Bearer token
     */
    protected function publishToLinkedIn(MarketingAccount $account): array
    {
        $accessToken = $account->access_token;
        if (!$accessToken) {
            return ['error' => 'LinkedIn account has no access token'];
        }

        try {
            // LinkedIn author URN — stored in account_id
            // Accept formats: "urn:li:person:xxx", "urn:li:organization:xxx", "urn:li:company:xxx" (legacy), or just the ID
            $authorUrn = $account->account_id;
            if (!str_starts_with($authorUrn, 'urn:li:')) {
                $authorUrn = 'urn:li:person:' . $authorUrn;
            }
            // Convert legacy "company" URN to new "organization" URN (required by /rest/posts API)
            $authorUrn = str_replace('urn:li:company:', 'urn:li:organization:', $authorUrn);

            // Use the stable /v2/ugcPosts endpoint (no version header needed, more reliable than /rest/posts)
            $shareContent = [
                'shareCommentary'    => ['text' => $this->content],
                'shareMediaCategory' => 'NONE',
            ];

            // If media is present, register/upload and attach it
            if ($this->mediaUrl) {
                $mediaAsset = $this->uploadLinkedInMedia($account, $authorUrn);
                if ($mediaAsset) {
                    $shareContent['shareMediaCategory'] = $this->mediaType === 'video' ? 'VIDEO' : 'IMAGE';
                    $shareContent['media'] = [[
                        'status'      => 'READY',
                        'description' => ['text' => ''],
                        'media'       => $mediaAsset,
                        'title'       => ['text' => ''],
                    ]];
                }
            }

            $postData = [
                'author'         => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => $shareContent,
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ];

            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type'              => 'application/json',
                ])
                ->post('https://api.linkedin.com/v2/ugcPosts', $postData);

            if ($response->successful() || $response->status() === 201) {
                // LinkedIn returns the post URN in the x-restli-id header (or in 'id' field)
                $postUrn = $response->header('x-restli-id') ?? $response->json('id') ?? null;
                return ['id' => $postUrn];
            }

            return ['error' => $response->json('message') ?? 'LinkedIn API error: ' . $response->status() . ' - ' . $response->body()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function uploadLinkedInMedia(MarketingAccount $account, string $authorUrn): ?string
    {
        try {
            $recipe = $this->mediaType === 'video'
                ? 'urn:li:digitalmediaRecipe:feedshare-video'
                : 'urn:li:digitalmediaRecipe:feedshare-image';

            // Step 1: Register the upload via stable v2 assets API
            $initResponse = \Illuminate\Support\Facades\Http::withToken($account->access_token)
                ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
                ->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                    'registerUploadRequest' => [
                        'owner'                => $authorUrn,
                        'recipes'              => [$recipe],
                        'serviceRelationships' => [[
                            'identifier'        => 'urn:li:userGeneratedContent',
                            'relationshipType'  => 'OWNER',
                        ]],
                    ],
                ]);

            if (!$initResponse->successful()) {
                Log::warning('LinkedIn media init failed: ' . $initResponse->body());
                return null;
            }

            $uploadUrl = data_get($initResponse->json(), 'value.uploadMechanism.com\.linkedin\.digitalmedia\.uploading\.MediaUploadHttpRequest.uploadUrl');
            $assetUrn  = $initResponse->json('value.asset');

            if (!$uploadUrl || !$assetUrn) {
                Log::warning('LinkedIn media init missing uploadUrl/asset: ' . $initResponse->body());
                return null;
            }

            // Step 2: Upload the binary
            $mediaContent = @file_get_contents($this->mediaUrl);
            if (!$mediaContent) {
                Log::warning('LinkedIn media: could not download from ' . $this->mediaUrl);
                return null;
            }

            $uploadResponse = \Illuminate\Support\Facades\Http::withToken($account->access_token)
                ->withBody($mediaContent, 'application/octet-stream')
                ->put($uploadUrl);

            if ($uploadResponse->successful() || $uploadResponse->status() === 201) {
                return $assetUrn;
            }

            Log::warning('LinkedIn media upload failed: HTTP ' . $uploadResponse->status());
            return null;
        } catch (\Exception $e) {
            Log::warning('LinkedIn media upload exception: ' . $e->getMessage());
            return null;
        }
    }
}
