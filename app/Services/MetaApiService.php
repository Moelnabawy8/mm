<?php

namespace App\Services;

use App\Models\MarketingAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignResult;
use App\Models\MarketingPost;
use App\Models\MarketingInsight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaApiService
{
    protected $baseUrl = 'https://graph.facebook.com/v23.0';
    
    public function exchangeCodeForToken($code, $redirectUri)
    {
        try {
            $response = Http::get($this->baseUrl . '/oauth/access_token', [
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Meta API Token Exchange Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getLongLivedToken($shortLivedToken)
    {
        try {
            $response = Http::get($this->baseUrl . '/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.meta.app_id'),
                'client_secret' => config('services.meta.app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Meta API Long-Lived Token Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getPages($accessToken)
    {
        try {
            $response = Http::get($this->baseUrl . '/me/accounts', [
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Meta API Path Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getInstagramAccounts($pageId, $accessToken)
    {
        try {
            $response = Http::get($this->baseUrl . "/{$pageId}", [
                'fields' => 'instagram_business_account',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Meta API Get Instagram Accounts Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getCampaigns($accountId, $accessToken)
    {
        try {
            $response = Http::get($this->baseUrl . "/act_{$accountId}/campaigns", [
                'fields' => 'id,name,objective,status,daily_budget,lifetime_budget,start_time,stop_time',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Meta API Get Campaigns Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getCampaignInsights($campaignId, $accessToken, $datePreset = 'last_30d')
    {
        try {
            $response = Http::get($this->baseUrl . "/{$campaignId}/insights", [
                'fields' => 'impressions,clicks,reach,spend,cpc,cpm,ctr,actions,action_values',
                'date_preset' => $datePreset,
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json()['data'][0] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Meta API Get Campaign Insights Error: ' . $e->getMessage());
            return null;
        }
    }

    public function getPageInsights($pageId, $accessToken, $since, $until)
    {
        try {
            $response = Http::get($this->baseUrl . "/{$pageId}/insights", [
                'metric' => 'page_impressions,page_engaged_users,page_post_engagements,page_fans',
                'since' => $since,
                'until' => $until,
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Meta API Get Page Insights Error: ' . $e->getMessage());
            return [];
        }
    }

    public function publishPost($pageId, $accessToken, $message, $mediaUrl = null, $scheduledPublishTime = null, $mediaType = 'image')
    {
        try {
            $params = [
                'access_token' => $accessToken,
            ];

            // Handle Scheduling (v23.0 logic)
            if ($scheduledPublishTime) {
                // scheduledPublishTime must be Unix timestamp in seconds
                $params['published'] = false;
                $params['scheduled_publish_time'] = $scheduledPublishTime;
            }

            if ($mediaUrl) {
                if ($mediaType === 'video') {
                    $endpoint = $this->baseUrl . "/{$pageId}/videos";
                    $params['file_url'] = $mediaUrl;
                    $params['description'] = $message;
                    $params['title'] = substr($message, 0, 50); // Optional title from content
                } else {
                    $endpoint = $this->baseUrl . "/{$pageId}/photos";
                    $params['url'] = $mediaUrl;
                    $params['caption'] = $message;
                }
            } else {
                $endpoint = $this->baseUrl . "/{$pageId}/feed";
                $params['message'] = $message;
            }

            $response = Http::post($endpoint, $params);

            if ($response->successful()) {
                $data = $response->json();
                // For photo posts, Facebook returns photo_id as 'id' and the actual feed post_id as 'post_id'
                // We need the post_id for fetching stats (likes, comments, shares)
                if (isset($data['post_id'])) {
                    $data['feed_post_id'] = $data['post_id'];
                }
                return $data;
            }

            Log::error('Meta API Publish Post Error Response: ' . $response->body());

            return ['error' => $response->json()['error']['message'] ?? 'Unknown error from Meta API'];
        } catch (\Exception $e) {
            Log::error('Meta API Publish Post Exception Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function updatePost($postId, $accessToken, $message, $scheduledPublishTime = null, $mediaType = 'image')
    {
        try {
            $params = [
                'access_token' => $accessToken,
            ];

            // For feed posts and videos, use 'message'; for photos use 'message' too (caption is read-only on update)
            $params['message'] = $message;

            if ($scheduledPublishTime) {
                $params['scheduled_publish_time'] = $scheduledPublishTime;
            }

            $response = Http::post($this->baseUrl . "/{$postId}", $params);

            if ($response->successful()) {
                return true;
            }

            $error = $response->json()['error']['message'] ?? 'Unknown error';
            Log::error('Meta API Update Post Error Response: ' . $response->body());
            return ['error' => $error];
        } catch (\Exception $e) {
            Log::error('Meta API Update Post Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Reschedule a scheduled post by deleting and recreating it.
     * Facebook does not support updating scheduled_publish_time on existing scheduled posts.
     */
    public function reschedulePost($postId, $pageId, $accessToken, $message, $scheduledPublishTime, $mediaUrl = null, $mediaType = 'image')
    {
        try {
            // Step 1: Delete the old scheduled post
            $deleted = $this->deletePost($postId, $accessToken);
            if (!$deleted) {
                Log::warning('Meta API Reschedule: Could not delete old post ' . $postId . ', proceeding with new post anyway');
            }

            // Step 2: Create a new scheduled post
            return $this->publishPost($pageId, $accessToken, $message, $mediaUrl, $scheduledPublishTime, $mediaType);
        } catch (\Exception $e) {
            Log::error('Meta API Reschedule Post Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function publishScheduledPostNow($postId, $accessToken)
    {
        try {
            $response = Http::post($this->baseUrl . "/{$postId}", [
                'is_published' => true,
                'access_token' => $accessToken,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Meta API Publish Now Error: ' . $e->getMessage());
            return false;
        }
    }

    public function deletePost($postId, $accessToken)
    {
        try {
            // Note: For DELETE requests, access_token must be a query parameter (v23.0 logic)
            $response = Http::delete($this->baseUrl . "/{$postId}?access_token={$accessToken}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Meta API Delete Post Error: ' . $e->getMessage());
            return false;
        }
    }

    public function publishInstagramPost($igUserId, $accessToken, $caption, $mediaUrl, $mediaType = 'image', $scheduledPublishTime = null)
    {
        try {
            // Step 1: Create Media Container (v23.0 logic)
            $containerParams = [
                'caption' => $caption,
                'access_token' => $accessToken,
            ];

            // Add Scheduling (v23.0 logic)
            if ($scheduledPublishTime) {
                $containerParams['scheduled_publish_time'] = $scheduledPublishTime;
            }

            if ($mediaType === 'video') {
                $containerParams['video_url'] = $mediaUrl;
                $containerParams['media_type'] = 'REELS'; // Default to Reels for better reach
            } else {
                $containerParams['image_url'] = $mediaUrl;
            }

            $containerResponse = Http::post($this->baseUrl . "/{$igUserId}/media", $containerParams);

            if (!$containerResponse->successful()) {
                Log::error('Meta API Instagram Container Error: ' . $containerResponse->body());
                return ['error' => 'Failed to create Instagram media container: ' . ($containerResponse->json()['error']['message'] ?? 'Unknown error')];
            }

            // Note: Scheduled posts skip step 2 as per the documentation
            if ($scheduledPublishTime) {
                return $containerResponse->json();
            }

            $creationId = $containerResponse->json()['id'];

            // Step 2: Publish the Container (v23.0 logic)
            $publishResponse = Http::post($this->baseUrl . "/{$igUserId}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $accessToken,
            ]);

            if ($publishResponse->successful()) {
                return $publishResponse->json();
            }

            Log::error('Meta API Instagram Publish Error: ' . $publishResponse->body());
            return ['error' => $publishResponse->json()['error']['message'] ?? 'Unknown error during Instagram publishing'];
        } catch (\Exception $e) {
            Log::error('Meta API Instagram Exception Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function publishInstagramCarousel($igUserId, $accessToken, $caption, array $mediaUrls, $scheduledPublishTime = null)
    {
        try {
            $itemIds = [];

            // Step 1: Upload each item to /media with is_carousel_item=true (v23.0 logic)
            foreach ($mediaUrls as $url) {
                $response = Http::post($this->baseUrl . "/{$igUserId}/media", [
                    'image_url' => $url,
                    'is_carousel_item' => true,
                    'access_token' => $accessToken,
                ]);

                if ($response->successful()) {
                    $itemIds[] = $response->json()['id'];
                } else {
                    Log::error('Meta API IG Carousel Item Error: ' . $response->body());
                    return ['error' => 'Failed to upload IG carousel item: ' . ($response->json()['error']['message'] ?? 'Unknown error')];
                }
            }

            // Step 2: Create the carousel container (v23.0 logic)
            $containerParams = [
                'media_type' => 'CAROUSEL',
                'children' => $itemIds,
                'caption' => $caption,
                'access_token' => $accessToken,
            ];

            if ($scheduledPublishTime) {
                $containerParams['scheduled_publish_time'] = $scheduledPublishTime;
            }

            $containerResponse = Http::post($this->baseUrl . "/{$igUserId}/media", $containerParams);

            if (!$containerResponse->successful()) {
                Log::error('Meta API IG Carousel Container Error: ' . $containerResponse->body());
                return ['error' => 'Failed to create IG carousel container: ' . ($containerResponse->json()['error']['message'] ?? 'Unknown error')];
            }

            // Step 3: Publish (skip if scheduled)
            if ($scheduledPublishTime) {
                return $containerResponse->json();
            }

            $creationId = $containerResponse->json()['id'];

            $publishResponse = Http::post($this->baseUrl . "/{$igUserId}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $accessToken,
            ]);

            if ($publishResponse->successful()) {
                return $publishResponse->json();
            }

            return ['error' => $publishResponse->json()['error']['message'] ?? 'Unknown error during IG carousel publish'];
        } catch (\Exception $e) {
            Log::error('Meta API IG Carousel Exception Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function publishCarousel($pageId, $accessToken, $message, array $mediaUrls, $scheduledPublishTime = null)
    {
        try {
            $mediaFbids = [];

            // Step 1: Upload each image individually with published=false (v23.0 logic)
            foreach ($mediaUrls as $url) {
                $response = Http::post($this->baseUrl . "/{$pageId}/photos", [
                    'url' => $url,
                    'published' => false,
                    'access_token' => $accessToken,
                ]);

                if ($response->successful()) {
                    $mediaFbids[] = ['media_fbid' => $response->json()['id']];
                } else {
                    Log::error('Meta API Carousel Image Upload Error: ' . $response->body());
                    return ['error' => 'Failed to upload one or more images for carousel: ' . ($response->json()['error']['message'] ?? 'Unknown error')];
                }
            }

            // Step 2: Create the final carousel post (v23.0 logic)
            $params = [
                'message' => $message,
                'attached_media' => $mediaFbids,
                'access_token' => $accessToken,
            ];

            if ($scheduledPublishTime) {
                $params['published'] = 'false';
                $params['scheduled_publish_time'] = $scheduledPublishTime;
            }

            $response = Http::post($this->baseUrl . "/{$pageId}/feed", $params);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['post_id'])) {
                    $data['feed_post_id'] = $data['post_id'];
                }
                return $data;
            }

            Log::error('Meta API Carousel Final Post Error: ' . $response->body());
            return ['error' => $response->json()['error']['message'] ?? 'Unknown error from Meta API'];
        } catch (\Exception $e) {
            Log::error('Meta API Carousel Exception Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getPostInsights($postId, $accessToken)
    {
        try {
            $response = Http::get($this->baseUrl . "/{$postId}", [
                'fields' => 'likes.summary(true),comments.summary(true),shares,reactions.summary(true)',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Meta API Get Post Insights Error: ' . $e->getMessage());
            return null;
        }
    }

    public function syncCampaignResults(MarketingCampaign $campaign)
    {
        $account = $campaign->account;
        
        if (!$account || !$account->access_token) {
            return false;
        }

        $insights = $this->getCampaignInsights(
            $campaign->campaign_id,
            $account->access_token
        );

        if (!$insights) {
            return false;
        }

        $conversions = 0;
        if (isset($insights['actions'])) {
            foreach ($insights['actions'] as $action) {
                if (in_array($action['action_type'], ['purchase', 'lead', 'complete_registration'])) {
                    $conversions += (int)$action['value'];
                }
            }
        }

        MarketingCampaignResult::updateOrCreate(
            [
                'campaign_id' => $campaign->id,
                'date' => Carbon::today(),
            ],
            [
                'impressions' => $insights['impressions'] ?? 0,
                'clicks' => $insights['clicks'] ?? 0,
                'reach' => $insights['reach'] ?? 0,
                'spend' => $insights['spend'] ?? 0,
                'cpc' => $insights['cpc'] ?? 0,
                'cpm' => $insights['cpm'] ?? 0,
                'ctr' => $insights['ctr'] ?? 0,
                'conversions' => $conversions,
            ]
        );

        return true;
    }

    public function syncPageInsights(MarketingAccount $account)
    {
        if (!$account->page_id || !$account->access_token) {
            return false;
        }

        $since = Carbon::yesterday()->format('Y-m-d');
        $until = Carbon::today()->format('Y-m-d');

        $insights = $this->getPageInsights(
            $account->page_id,
            $account->access_token,
            $since,
            $until
        );

        if (empty($insights)) {
            return false;
        }

        $data = [];
        foreach ($insights as $metric) {
            $metricName = $metric['name'];
            $value = $metric['values'][0]['value'] ?? 0;
            $data[$metricName] = $value;
        }

        MarketingInsight::updateOrCreate(
            [
                'account_id' => $account->id,
                'date' => Carbon::yesterday(),
            ],
            [
                'followers' => $data['page_fans'] ?? 0,
                'page_views' => $data['page_impressions'] ?? 0,
                'total_engagement' => $data['page_post_engagements'] ?? 0,
            ]
        );

        return true;
    }

    public function getPostStats($postId, $accessToken)
    {
        try {
            // Try fetching with shares (works for feed posts and photos)
            $response = Http::get($this->baseUrl . "/{$postId}", [
                'fields' => 'id,likes.summary(true),comments.summary(true),shares',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'likes' => $data['likes']['summary']['total_count'] ?? 0,
                    'comments' => $data['comments']['summary']['total_count'] ?? 0,
                    'shares' => $data['shares']['count'] ?? 0,
                ];
            }

            $errorMsg = $response->json()['error']['message'] ?? '';

            // Reels don't support the 'shares' field — retry without it
            if (str_contains($errorMsg, 'shares') || str_contains($errorMsg, 'nonexisting field')) {
                Log::info('Meta API: Retrying stats without shares field (Reels) for ' . $postId);
                $reelsResponse = Http::get($this->baseUrl . "/{$postId}", [
                    'fields' => 'id,likes.summary(true),comments.summary(true)',
                    'access_token' => $accessToken,
                ]);

                if ($reelsResponse->successful()) {
                    $data = $reelsResponse->json();
                    return [
                        'likes' => $data['likes']['summary']['total_count'] ?? 0,
                        'comments' => $data['comments']['summary']['total_count'] ?? 0,
                        'shares' => 0,
                    ];
                }
            }

            // If the above fails, it might be a photo_id — try to get the feed post_id from it
            Log::info('Meta API: First stats attempt failed for ' . $postId . ', trying as photo_id...');
            $photoResponse = Http::get($this->baseUrl . "/{$postId}", [
                'fields' => 'id,page_story_id',
                'access_token' => $accessToken,
            ]);

            if ($photoResponse->successful()) {
                $photoData = $photoResponse->json();
                $feedPostId = $photoData['page_story_id'] ?? null;

                if ($feedPostId) {
                    $retryResponse = Http::get($this->baseUrl . "/{$feedPostId}", [
                        'fields' => 'id,likes.summary(true),comments.summary(true),shares',
                        'access_token' => $accessToken,
                    ]);

                    if ($retryResponse->successful()) {
                        $retryData = $retryResponse->json();
                        return [
                            'likes' => $retryData['likes']['summary']['total_count'] ?? 0,
                            'comments' => $retryData['comments']['summary']['total_count'] ?? 0,
                            'shares' => $retryData['shares']['count'] ?? 0,
                        ];
                    }
                }
            }

            Log::error('Meta API Get Stats Failed: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Meta API Get Stats Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getInstagramPostStats($mediaId, $accessToken)
    {
        try {
            $response = Http::get($this->baseUrl . "/{$mediaId}", [
                'fields' => 'id,like_count,comments_count,shares_count',
                'access_token' => $accessToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'likes' => $data['like_count'] ?? 0,
                    'comments' => $data['comments_count'] ?? 0,
                    'shares' => $data['shares_count'] ?? 0,
                ];
            }
            Log::error('Meta API Get IG Stats Failed: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Meta API Get IG Stats Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Facebook Page Insights Summary
     * NOTE: Many old page_* metrics were deprecated in v18.0+
     * We now get followers/fans from the page object and calculate
     * engagement from recent posts as a fallback.
     */
    /**
     * Get Facebook Page Insights Summary.
     *
     * @param int|null $since Unix timestamp for the start of the window. When provided,
     *                        metrics are computed for the range [$since, $until]. When
     *                        null, falls back to the legacy "last 10 posts +
     *                        talking_about_count" snapshot (used by manual sync).
     * @param int|null $until Unix timestamp for the end of the window (defaults to now).
     */
    public function getPageInsightsSummary($pageId, $accessToken, $since = null, $until = null, $followsAsTotal = false, $chunkDays = 30, $throttleMs = 0, $failOnEmpty = false)
    {
        $until = $until ?: time();
        $chunkDays = max(1, min(80, (int) $chunkDays)); // Graph caps a single insights call near ~90 days
        try {
            // Step 1: Page object — valid fields only (posts_count is NOT a valid field)
            $pageResponse = Http::get($this->baseUrl . "/{$pageId}", [
                'fields' => 'fan_count,followers_count,talking_about_count',
                'access_token' => $accessToken,
            ]);

            $followers      = 0;
            $talkingAbout   = 0;
            if ($pageResponse->successful()) {
                $d = $pageResponse->json();
                $followers     = $d['followers_count'] ?? $d['fan_count'] ?? 0;
                $talkingAbout  = $d['talking_about_count'] ?? 0;
            } else {
                Log::warning('Meta API FB page object failed: ' . $pageResponse->body());
                // A failure on the page object itself means the stored token is invalid,
                // expired, or no longer has access to this page. Surface it as an error so
                // the dashboard shows "reconnect needed" instead of misleading all-zero stats.
                $err = $pageResponse->json()['error'] ?? [];
                return ['error' => $err['message'] ?? __('Access token invalid or expired — please reconnect this page.')];
            }

            // Step 2: Aggregate likes+comments+shares from posts in the selected window
            $engagement  = 0;
            $postsCount  = 0;
            $reach       = 0;
            $impressions = 0;
            $videoViews  = 0;
            $pageViews   = 0;

            $postParams = [
                'fields'       => 'id,likes.summary(true),comments.summary(true),shares',
                'access_token' => $accessToken,
            ];

            // Single page only — engagement now comes from the page_post_engagements
            // insight metric below, so this call is just for posts_count and as a
            // fallback. Paginating hundreds of posts per account was too slow and
            // triggered connection resets on large pages.
            if ($since) {
                $postParams['since'] = $since;
                $postParams['until'] = $until;
                $postParams['limit'] = 50;
            } else {
                $postParams['limit'] = 10;
            }

            $postInteractions = 0;
            $postsResponse = Http::get($this->baseUrl . "/{$pageId}/posts", $postParams);
            if ($postsResponse->successful()) {
                foreach ($postsResponse->json()['data'] ?? [] as $post) {
                    $postInteractions += $post['likes']['summary']['total_count'] ?? 0;
                    $postInteractions += $post['comments']['summary']['total_count'] ?? 0;
                    $postInteractions += $post['shares']['count'] ?? 0;
                    $postsCount++;
                }
            } else {
                Log::warning('Meta API FB posts failed: ' . $postsResponse->body());
            }

            if ($since) {
                // Engagement for the period = real interactions on posts in that window
                // (used as the fallback if the page-level metric is unavailable).
                $engagement = $postInteractions;

                // Step 3: Page-level metrics for the window.
                // NOTE: only these metrics are still valid in v23.0 — page_impressions
                // (total) and many others were deprecated. A single invalid metric in the
                // list makes the whole call fail, so keep this list to known-good values.
                //
                // IMPORTANT: the Graph API silently returns an empty data set when the
                // since→until span exceeds ~90 days, which used to drop every page back to
                // the tiny post-interaction fallback (e.g. reach=6 instead of 21.6K).
                // Split long ranges into <=30-day chunks and sum them, exactly like
                // getInstagramInsights() does.
                // Each metric accumulated separately across the 30-day chunks.
                // Validated against v23.0 live API: clicks (page_consumptions) and referral
                // traffic (page_views_external_referrals) are deprecated and always return 0,
                // so they are surfaced as null ("N/A") instead of a misleading zero.
                $reachSum = $postReachSum = $engSum = $clicksSum = $viewsSum = $videoSum = $referralSum = $followsGainedSum = 0;
                $okChunks = 0;

                $cursor = $since;
                while ($cursor < $until) {
                    $chunkEnd = min($cursor + ($chunkDays * 86400), $until);

                    $insightsResponse = Http::get($this->baseUrl . "/{$pageId}/insights", [
                        'metric'       => 'page_impressions_unique,page_posts_impressions_unique,page_post_engagements,page_consumptions,page_views_total,page_video_views,page_views_external_referrals',
                        'period'       => 'day',
                        'since'        => $cursor,
                        'until'        => $chunkEnd,
                        'access_token' => $accessToken,
                    ]);

                    if ($insightsResponse->successful()) {
                        $okChunks++;
                        foreach ($insightsResponse->json()['data'] ?? [] as $metric) {
                            $sum = 0;
                            foreach ($metric['values'] ?? [] as $v) {
                                $sum += is_array($v['value']) ? array_sum($v['value']) : (int) $v['value'];
                            }
                            match ($metric['name']) {
                                'page_impressions_unique'        => $reachSum     += $sum,
                                'page_posts_impressions_unique'  => $postReachSum += $sum,
                                'page_post_engagements'          => $engSum       += $sum,
                                'page_consumptions'              => $clicksSum    += $sum,
                                'page_views_total'               => $viewsSum     += $sum,
                                'page_video_views'               => $videoSum     += $sum,
                                'page_views_external_referrals'  => $referralSum  += $sum,
                                default => null,
                            };
                        }
                    } else {
                        Log::warning('Meta API FB page insights (period) failed: ' . $insightsResponse->body());
                    }

                    // page_daily_follows_unique (NEW follows in the window) must be queried
                    // on its own — bundling it into the batch above triggers a (#100)
                    // "valid insights metric" error that nukes the whole response. Skipped
                    // when we only need the live total (followsAsTotal) to save a call.
                    if (!$followsAsTotal) {
                        $followsResponse = Http::get($this->baseUrl . "/{$pageId}/insights", [
                            'metric'       => 'page_daily_follows_unique',
                            'period'       => 'day',
                            'since'        => $cursor,
                            'until'        => $chunkEnd,
                            'access_token' => $accessToken,
                        ]);
                        if ($followsResponse->successful()) {
                            foreach ($followsResponse->json()['data'][0]['values'] ?? [] as $v) {
                                $followsGainedSum += is_array($v['value']) ? array_sum($v['value']) : (int) $v['value'];
                            }
                        }
                    }

                    $cursor = $chunkEnd;

                    if ($throttleMs > 0 && $cursor < $until) {
                        usleep($throttleMs * 1000); // pace the nightly all-time job to avoid rate limits
                    }
                }

                // For the heavy all-time job: if Graph never returned data (rate limited),
                // fail loudly instead of storing the tiny post-interaction fallback.
                if ($failOnEmpty && $okChunks === 0) {
                    return ['error' => 'Insights unavailable (rate limited) — not stored'];
                }

                $reach      = $reachSum;
                $postReach  = $postReachSum ?: $reachSum;
                $videoViews = $videoSum;
                $pageViews  = $viewsSum;
                if ($engSum > 0) {
                    $engagement = $engSum;
                }

                // Impressions metric is deprecated in v23 — use unique reach as the proxy.
                $impressions = $reach;

                // Fallbacks when page-level data is empty/unavailable
                if ($engagement === 0)  $engagement  = $postInteractions;
                if ($reach === 0)       $reach       = $postInteractions;
                if ($postReach === 0)   $postReach   = $postInteractions;
                if ($impressions === 0) $impressions = $postInteractions;

                $engagementRate = $followers > 0 ? round(($engagement / $followers) * 100, 2) : 0;

                Log::info("Meta API FB Insights: window=" . date('Y-m-d', $since) . '..' . date('Y-m-d', $until) . ", followers={$followers}, post_reach={$postReach}, engagement={$engagement}, posts={$postsCount}");

                return [
                    // legacy keys (kept for snapshot table / other callers)
                    'followers'        => $followers,
                    'reach'            => $reach,
                    'impressions'      => $impressions,
                    'page_views'       => $pageViews,
                    'video_views'      => $videoViews,
                    'engagement'       => $engagement,
                    'posts_count'      => $postsCount,
                    'engagement_rate'  => $engagementRate,
                    // new professional metric set (Facebook)
                    // Page Follows = NEW follows gained within the selected window
                    // (page_daily_follows_unique). In real-time mode it becomes the live
                    // total follower count instead.
                    'follows'          => $followsAsTotal ? $followers : $followsGainedSum,
                    'post_reach'       => $postReach,
                    'engagements'      => $engagement,
                    'clicks'           => $clicksSum ?: null,        // deprecated by Meta -> N/A
                    'referral_traffic' => $referralSum ?: null,      // deprecated by Meta -> N/A
                ];
            } else {
                // Legacy snapshot behavior (used by manual sync)
                $engagement  = $talkingAbout;
                $reach       = $postInteractions;
                $impressions = $postInteractions;
            }

            $engagementRate = $followers > 0 ? round(($engagement / $followers) * 100, 2) : 0;

            Log::info("Meta API FB Insights: snapshot, followers={$followers}, engagement={$engagement}, reach={$reach}, posts={$postsCount}");

            return [
                'followers'       => $followers,
                'reach'           => $reach,
                'impressions'     => $impressions,
                'page_views'      => $pageViews,
                'video_views'     => $videoViews,
                'engagement'      => $engagement,
                'posts_count'     => $postsCount,
                'engagement_rate' => $engagementRate,
            ];
        } catch (\Exception $e) {
            Log::error('Meta API getPageInsightsSummary Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Instagram Business Account Insights
     * Valid metrics (v23.0): reach, views, profile_views, website_clicks,
     *   accounts_engaged, total_interactions, likes, comments, shares, saves
     */
    /**
     * Get Instagram Business Account Insights.
     *
     * @param int|null $since Unix timestamp for the start of the window (defaults to 7
     *                        days ago, preserving the legacy manual-sync behavior).
     * @param int|null $until Unix timestamp for the end of the window (defaults to now).
     *                        The Graph API caps each insights query at a 30-day window,
     *                        so longer ranges are fetched in 30-day chunks and summed.
     */
    public function getInstagramInsights($igUserId, $accessToken, $since = null, $until = null, $followsAsTotal = false, $throttleMs = 0, $failOnEmpty = false)
    {
        try {
            $until = $until ?: time();
            $start = $since ?: strtotime('-7 days');

            // Step 1: Followers from account object (always works)
            $accountResponse = Http::get($this->baseUrl . "/{$igUserId}", [
                'fields' => 'followers_count,media_count,name',
                'access_token' => $accessToken,
            ]);

            $followers = 0;
            if ($accountResponse->successful()) {
                $followers = $accountResponse->json()['followers_count'] ?? 0;
            } else {
                Log::warning('Meta API IG account object failed: ' . $accountResponse->body());
                // Failure on the account object means the stored token is invalid/expired or
                // the linked Facebook Page is no longer granted. Surface it instead of zeros.
                $err = $accountResponse->json()['error'] ?? [];
                return ['error' => $err['message'] ?? __('Access token invalid or expired — please reconnect this account.')];
            }

            $reach = $views = $profileViews = $interactions = $clicks = $followsGained = 0;
            $okChunks = 0;

            // Build 30-day chunks covering the whole window (IG caps each insights query at 30 days)
            $chunks = [];
            $cursor = $start;
            while ($cursor < $until) {
                $chunkEnd = min($cursor + (30 * 86400), $until);
                $chunks[] = [$cursor, $chunkEnd];
                $cursor = $chunkEnd;
            }

            foreach ($chunks as $ci => [$since, $end]) {
                // Step 2a: period=day metrics. 'reach' = unique reach; 'follower_count' =
                // NEW followers per day, summed to get follows gained in the window so the
                // "Page Follows" tile reflects the selected period.
                $reachResponse = Http::get($this->baseUrl . "/{$igUserId}/insights", [
                    'metric' => 'reach,follower_count',
                    'period' => 'day',
                    'since'  => $since,
                    'until'  => $end,
                    'access_token' => $accessToken,
                ]);

                if ($reachResponse->successful()) {
                    $okChunks++;
                    foreach ($reachResponse->json()['data'] ?? [] as $metric) {
                        $sum = 0;
                        foreach ($metric['values'] ?? [] as $v) {
                            $sum += is_array($v['value']) ? array_sum($v['value']) : (int)$v['value'];
                        }
                        match ($metric['name']) {
                            'reach'          => $reach        += $sum,
                            'follower_count' => $followsGained += $sum,
                            default => null,
                        };
                    }
                }

                // Step 2b: Other metrics require metric_type=total_value AND period=day
                $totalResponse = Http::get($this->baseUrl . "/{$igUserId}/insights", [
                    'metric'      => 'views,profile_views,total_interactions,accounts_engaged,website_clicks',
                    'metric_type' => 'total_value',
                    'period'      => 'day',
                    'since'       => $since,
                    'until'       => $end,
                    'access_token' => $accessToken,
                ]);

                if ($totalResponse->successful()) {
                    foreach ($totalResponse->json()['data'] ?? [] as $item) {
                        $tv = $item['total_value'] ?? [];

                        // Try direct value first
                        $value = isset($tv['value']) ? (int)$tv['value'] : null;

                        // Fall back to summing breakdown results (API sometimes returns this format)
                        if ($value === null && isset($tv['breakdowns'])) {
                            $value = 0;
                            foreach ($tv['breakdowns'] as $bd) {
                                foreach ($bd['results'] ?? [] as $result) {
                                    $value += (int)($result['value'] ?? 0);
                                }
                            }
                        }

                        $value = (int)($value ?? 0);
                        match ($item['name']) {
                            'views'               => $views += $value,
                            'profile_views'       => $profileViews += $value,
                            'total_interactions'  => $interactions += $value,
                            'accounts_engaged'    => $interactions = $interactions ?: $value,
                            'website_clicks'      => $clicks += $value,
                            default => null,
                        };
                    }
                } else {
                    Log::warning('Meta API IG total_value insights failed: ' . $totalResponse->body());
                }

                if ($throttleMs > 0 && $ci < count($chunks) - 1) {
                    usleep($throttleMs * 1000); // pace the nightly all-time job
                }
            }

            // For the heavy all-time job: bail out instead of storing empty numbers when
            // Graph never returned data (rate limited).
            if ($failOnEmpty && $okChunks === 0) {
                return ['error' => 'Insights unavailable (rate limited) — not stored'];
            }

            // Step 3: Reels views — Reels plays/views are per-media, so aggregate them by
            // pulling the account's media with their insights INLINE (1 request per page,
            // not 1 per reel) and summing the `views` of every REELS item inside the window.
            // Capped at a few pages to stay fast; only reels created within [start, until].
            $reelsViews = 0;
            $reelsFound = false;
            $mediaUrl = $this->baseUrl . "/{$igUserId}/media?fields=media_product_type,timestamp,insights.metric(views)&limit=50&access_token=" . urlencode($accessToken);
            for ($page = 0; $page < 6 && $mediaUrl; $page++) {
                $mediaResp = Http::get($mediaUrl);
                if (!$mediaResp->successful()) {
                    Log::warning('Meta API IG media (reels) failed: ' . $mediaResp->body());
                    break;
                }
                $mediaJson = $mediaResp->json();
                $reachedOlder = false;
                foreach ($mediaJson['data'] ?? [] as $media) {
                    $ts = isset($media['timestamp']) ? strtotime($media['timestamp']) : 0;
                    if ($ts && $ts < $start) { $reachedOlder = true; continue; }
                    if ($ts > $until) { continue; }
                    if (($media['media_product_type'] ?? '') !== 'REELS') { continue; }
                    $reelsFound = true;
                    foreach ($media['insights']['data'] ?? [] as $ins) {
                        if ($ins['name'] === 'views') {
                            $reelsViews += (int) ($ins['values'][0]['value'] ?? 0);
                        }
                    }
                }
                // Media come newest-first; once we pass the start of the window, stop paging.
                $mediaUrl = $reachedOlder ? null : ($mediaJson['paging']['next'] ?? null);
            }

            $engagementRate = $followers > 0 ? round(($interactions / $followers) * 100, 2) : 0;

            Log::info("Meta API IG Insights: window=" . date('Y-m-d', $start) . '..' . date('Y-m-d', $until) . ", followers={$followers}, follows_gained={$followsGained}, reach={$reach}, views={$views}, reels_views={$reelsViews}, interactions={$interactions}");

            return [
                // legacy keys
                'followers'           => $followers,
                'reach'               => $reach,
                'impressions'         => $views,
                'profile_views'       => $profileViews,
                'page_views'          => $profileViews,
                'video_views'         => $views,
                'engagement'          => $interactions,
                'engagement_rate'     => $engagementRate,
                // new professional metric set (Instagram)
                // new follows in the window, or live total followers in real-time mode
                'follows'             => $followsAsTotal ? $followers : $followsGained,
                'post_reach'          => $reach,
                'interactions'        => $interactions,
                'clicks'              => $clicks,
                'reels_plays'         => $reelsFound ? $reelsViews : null, // Meta merged plays into views
                'reels_views'         => $reelsFound ? $reelsViews : null,
                // Genuinely unavailable historically in v23 -> N/A
                'stories_impressions' => null,  // Stories insights only exist for active (24h) stories
                'stories_engagement'  => null,
                'unfollows'           => null,  // no reliable per-period account unfollow metric
            ];
        } catch (\Exception $e) {
            Log::error('Meta API getInstagramInsights Error: ' . $e->getMessage());
            return false;
        }
    }
}
