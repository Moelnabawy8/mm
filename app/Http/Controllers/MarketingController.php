<?php

namespace App\Http\Controllers;

use App\Models\MarketingAccount;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignResult;
use App\Models\MarketingPost;
use App\Models\MarketingInsight;
use App\Models\MultiPostLog;
use App\Jobs\PublishToSocialPlatform;
use App\Services\MetaApiService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MarketingController extends Controller
{
    protected $metaApi;

    public function __construct(MetaApiService $metaApi)
    {
        $this->metaApi = $metaApi;
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        $accounts = MarketingAccount::where('created_by', $user->id)
            ->orWhere('created_by', $user->created_by ?: $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $campaigns = MarketingCampaign::whereIn('account_id', $accounts->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $totalAccounts = $accounts->count();
        $activeCampaigns = MarketingCampaign::whereIn('account_id', $accounts->pluck('id'))
            ->where('status', 'active')
            ->count();

        $totalSpent = MarketingCampaignResult::whereIn('campaign_id', function($query) use ($accounts) {
            $query->select('id')
                ->from('marketing_campaigns')
                ->whereIn('account_id', $accounts->pluck('id'));
        })->sum('spend');

        $totalImpressions = MarketingCampaignResult::whereIn('campaign_id', function($query) use ($accounts) {
            $query->select('id')
                ->from('marketing_campaigns')
                ->whereIn('account_id', $accounts->pluck('id'));
        })->sum('impressions');

        $recentResults = MarketingCampaignResult::whereIn('campaign_id', function($query) use ($accounts) {
            $query->select('id')
                ->from('marketing_campaigns')
                ->whereIn('account_id', $accounts->pluck('id'));
        })
        ->where('date', '>=', Carbon::now()->subDays(30))
        ->orderBy('date', 'desc')
        ->get()
        ->groupBy(function($item) {
            return $item->date->format('Y-m-d');
        });

        $chartData = [
            'labels' => [],
            'impressions' => [],
            'clicks' => [],
            'spend' => [],
        ];

        foreach ($recentResults as $date => $results) {
            $chartData['labels'][] = Carbon::parse($date)->format('M d');
            $chartData['impressions'][] = $results->sum('impressions');
            $chartData['clicks'][] = $results->sum('clicks');
            $chartData['spend'][] = $results->sum('spend');
        }

        return view('marketing.index', compact(
            'accounts',
            'campaigns',
            'totalAccounts',
            'activeCampaigns',
            'totalSpent',
            'totalImpressions',
            'chartData'
        ));
    }

    public function accounts()
    {
        $user = Auth::user();
        
        $accounts = MarketingAccount::where('created_by', $user->id)
            ->orWhere('created_by', $user->created_by ?: $user->id)
            ->with('campaigns')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('marketing.accounts.index', compact('accounts'));
    }

    public function createAccount()
    {
        $metaAppId = config('services.meta.app_id');
        return view('marketing.accounts.create', compact('metaAppId'));
    }

    public function storeAccount(Request $request)
    {
        // Support adding multiple accounts at once
        if ($request->has('accounts') && is_array($request->accounts)) {
            $addedCount = 0;
            foreach ($request->accounts as $accountData) {
                if (empty($accountData['platform']) || empty($accountData['account_name'])) {
                    continue;
                }
                
                // Check if account already exists for this user with same platform + page_id
                $exists = MarketingAccount::where('created_by', Auth::id())
                    ->where('platform', $accountData['platform'])
                    ->where('page_id', $accountData['page_id'] ?? null)
                    ->exists();
                
                if ($exists) {
                    continue;
                }
                
                MarketingAccount::create([
                    'created_by' => Auth::id(),
                    'platform' => $accountData['platform'],
                    'account_name' => $accountData['account_name'],
                    'account_id' => $accountData['account_id'] ?? null,
                    'access_token' => $accountData['access_token'] ?? null,
                    'page_id' => $accountData['page_id'] ?? null,
                    'page_name' => $accountData['page_name'] ?? null,
                    'status' => 'active',
                ]);
                $addedCount++;
            }
            
            if ($addedCount > 0) {
                return redirect()->route('marketing.accounts')->with('success', __(':count account(s) connected successfully', ['count' => $addedCount]));
            }
            return redirect()->route('marketing.accounts')->with('warning', __('No new accounts were added. They may already exist.'));
        }

        // Single account add
        $validator = Validator::make($request->all(), [
            'platform' => 'required|in:facebook,instagram,twitter,linkedin,youtube,tiktok',
            'account_name' => 'required|string|max:255',
            'account_id' => 'nullable|string|max:255',
            'access_token' => 'nullable|string',
            'page_id' => 'nullable|string|max:255',
            'page_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Check if account already exists
        $exists = MarketingAccount::where('created_by', Auth::id())
            ->where('platform', $request->platform)
            ->where('page_id', $request->page_id)
            ->when(!$request->page_id, function($q) use ($request) {
                return $q->where('account_name', $request->account_name);
            })
            ->exists();
        
        if ($exists) {
            return redirect()->back()->with('error', __('This account is already connected'))->withInput();
        }

        $account = MarketingAccount::create([
            'created_by' => Auth::id(),
            'platform' => $request->platform,
            'account_name' => $request->account_name,
            'account_id' => $request->account_id,
            'access_token' => $request->access_token,
            'page_id' => $request->page_id,
            'page_name' => $request->page_name,
            'status' => 'active',
        ]);

        return redirect()->route('marketing.accounts')->with('success', __('Account connected successfully'));
    }

    public function editAccount($id)
    {
        $account = MarketingAccount::findOrFail($id);
        
        if ($account->created_by != Auth::id() && $account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        return view('marketing.accounts.edit', compact('account'));
    }

    public function updateAccount(Request $request, $id)
    {
        $account = MarketingAccount::findOrFail($id);
        
        if ($account->created_by != Auth::id() && $account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'account_name' => 'required|string|max:255',
            'access_token' => 'nullable|string',
            'page_id' => 'nullable|string|max:255',
            'page_name' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,expired',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $account->update($request->only(['account_name', 'access_token', 'page_id', 'page_name', 'status']));

        return redirect()->route('marketing.accounts')->with('success', __('Account updated successfully'));
    }

    public function deleteAccount($id)
    {
        $account = MarketingAccount::findOrFail($id);
        
        if ($account->created_by != Auth::id() && $account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $account->delete();

        return redirect()->route('marketing.accounts')->with('success', __('Account deleted successfully'));
    }

    public function campaigns()
    {
        $user = Auth::user();
        
        $campaigns = MarketingCampaign::whereHas('account', function($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhere('created_by', $user->created_by ?: $user->id);
        })
        ->with(['account', 'results'])
        ->orderBy('created_at', 'desc')
        ->get();

        return view('marketing.campaigns.index', compact('campaigns'));
    }

    public function createCampaign()
    {
        $user = Auth::user();
        
        $accounts = MarketingAccount::where('created_by', $user->id)
            ->orWhere('created_by', $user->created_by ?: $user->id)
            ->where('status', 'active')
            ->get();

        return view('marketing.campaigns.create', compact('accounts'));
    }

    public function storeCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:marketing_accounts,id',
            'campaign_name' => 'required|string|max:255',
            'objective' => 'required|in:awareness,traffic,engagement,leads,sales,conversions',
            'budget' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $account = MarketingAccount::findOrFail($request->account_id);

        $campaign = MarketingCampaign::create([
            'created_by' => Auth::id(),
            'account_id' => $request->account_id,
            'campaign_name' => $request->campaign_name,
            'platform' => $account->platform,
            'objective' => $request->objective,
            'status' => 'draft',
            'budget' => $request->budget,
            'currency' => $request->currency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
        ]);

        return redirect()->route('marketing.campaigns')->with('success', __('Campaign created successfully'));
    }

    public function showCampaign($id)
    {
        $campaign = MarketingCampaign::with(['account', 'results', 'posts'])->findOrFail($id);
        
        if ($campaign->account->created_by != Auth::id() && $campaign->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $results = $campaign->results()
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        $chartData = [
            'labels' => $results->pluck('date')->map(fn($d) => $d->format('M d'))->reverse()->values(),
            'impressions' => $results->pluck('impressions')->reverse()->values(),
            'clicks' => $results->pluck('clicks')->reverse()->values(),
            'conversions' => $results->pluck('conversions')->reverse()->values(),
            'spend' => $results->pluck('spend')->reverse()->values(),
        ];

        return view('marketing.campaigns.show', compact('campaign', 'results', 'chartData'));
    }

    public function editCampaign($id)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        
        if ($campaign->account->created_by != Auth::id() && $campaign->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $user = Auth::user();
        $accounts = MarketingAccount::where('created_by', $user->id)
            ->orWhere('created_by', $user->created_by ?: $user->id)
            ->where('status', 'active')
            ->get();

        return view('marketing.campaigns.edit', compact('campaign', 'accounts'));
    }

    public function updateCampaign(Request $request, $id)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        
        if ($campaign->account->created_by != Auth::id() && $campaign->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'campaign_name' => 'required|string|max:255',
            'objective' => 'required|in:awareness,traffic,engagement,leads,sales,conversions',
            'budget' => 'required|numeric|min:0',
            'status' => 'required|in:draft,active,paused,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $campaign->update($request->only([
            'campaign_name',
            'objective',
            'budget',
            'status',
            'start_date',
            'end_date',
            'description'
        ]));

        return redirect()->route('marketing.campaigns.show', $id)->with('success', __('Campaign updated successfully'));
    }

    public function deleteCampaign($id)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        
        if ($campaign->account->created_by != Auth::id() && $campaign->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $campaign->delete();

        return redirect()->route('marketing.campaigns')->with('success', __('Campaign deleted successfully'));
    }

    public function syncCampaign($id)
    {
        $campaign = MarketingCampaign::findOrFail($id);
        
        if ($campaign->account->created_by != Auth::id() && $campaign->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $success = $this->metaApi->syncCampaignResults($campaign);

        if ($success) {
            return redirect()->back()->with('success', __('Campaign data synced successfully'));
        }

        return redirect()->back()->with('error', __('Failed to sync campaign data'));
    }

    public function posts()
    {
        $user = Auth::user();
        
        $posts = MarketingPost::whereHas('account', function($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhere('created_by', $user->created_by ?: $user->id);
        })
        ->with(['account', 'campaign'])
        ->orderBy('created_at', 'desc')
        ->get();

        return view('marketing.posts.index', compact('posts'));
    }

    public function createPost()
    {
        $user = Auth::user();
        
        $accounts = MarketingAccount::where('created_by', $user->id)
            ->orWhere('created_by', $user->created_by ?: $user->id)
            ->where('status', 'active')
            ->get();

        $campaigns = MarketingCampaign::whereIn('account_id', $accounts->pluck('id'))
            ->where('status', 'active')
            ->get();

        return view('marketing.posts.create', compact('accounts', 'campaigns'));
    }

    public function storePost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:marketing_accounts,id',
            'campaign_id' => 'nullable|exists:marketing_campaigns,id',
            'content' => 'required|string',
            'media_url' => 'nullable|string',
            'media_urls' => 'nullable|array',
            'media_type' => 'nullable|in:image,video,carousel',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $processedMediaUrl = $this->processMediaUrl($request->media_url);
            $processedMediaUrls = [];
            if ($request->media_type === 'carousel' && is_array($request->media_urls)) {
                foreach ($request->media_urls as $url) {
                    if (!empty($url)) {
                        $processedMediaUrls[] = $this->processMediaUrl($url);
                    }
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        $post = MarketingPost::create([
            'created_by' => Auth::id(),
            'account_id' => $request->account_id,
            'campaign_id' => $request->campaign_id,
            'content' => $request->content,
            'media_url' => $processedMediaUrl,
            'media_type' => $request->media_type ?? 'image',
            'metadata' => $request->media_type === 'carousel' ? ['urls' => $processedMediaUrls] : null,
            'status' => $request->scheduled_at ? 'scheduled' : 'draft',
            'scheduled_at' => $request->scheduled_at,
        ]);

        if ($request->scheduled_at || $request->has('publish_now')) {
            $result = $this->executePublish($post);
            if ($result !== true) {
                return redirect()->route('marketing.posts')->with('error', __('Post saved but failed to send to Meta: ') . $result);
            }
            return redirect()->route('marketing.posts')->with('success', __('Post successfully scheduled/published to Meta!'));
        }

        return redirect()->route('marketing.posts')->with('success', __('Post saved as draft successfully'));
    }

    public function publishPost($id)
    {
        $post = MarketingPost::findOrFail($id);
        
        if ($post->account->created_by != Auth::id() && $post->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $result = $this->executePublish($post);

        if ($result === true) {
            return redirect()->back()->with('success', __('Post successfully sent to Meta!'));
        }

        return redirect()->back()->with('error', $result ?: __('Failed to publish post'));
    }

    private function executePublish(MarketingPost $post)
    {
        $scheduledTime = $post->scheduled_at ? $post->scheduled_at->timestamp : null;

        if ($post->account->platform === 'instagram') {
            if ($post->media_type === 'carousel' && isset($post->metadata['urls'])) {
                $result = $this->metaApi->publishInstagramCarousel(
                    $post->account->page_id,
                    $post->account->access_token,
                    $post->content,
                    $post->metadata['urls'],
                    $scheduledTime
                );
            } else {
                $result = $this->metaApi->publishInstagramPost(
                    $post->account->page_id,
                    $post->account->access_token,
                    $post->content,
                    $post->media_url,
                    $post->media_type ?? 'image',
                    $scheduledTime
                );
            }
        } elseif ($post->media_type === 'carousel' && isset($post->metadata['urls'])) {
            $result = $this->metaApi->publishCarousel(
                $post->account->page_id,
                $post->account->access_token,
                $post->content,
                $post->metadata['urls'],
                $scheduledTime
            );
        } else {
            $result = $this->metaApi->publishPost(
                $post->account->page_id,
                $post->account->access_token,
                $post->content,
                $post->media_url,
                $scheduledTime,
                $post->media_type ?? 'image'
            );
        }

        if ($result && !isset($result['error'])) {
            // For photo posts, use feed_post_id (the actual post in the feed) for stats tracking
            // For other posts, use the regular id
            $postId = $result['feed_post_id'] ?? $result['post_id'] ?? $result['id'] ?? null;
            $post->update([
                'post_id' => $postId,
                'status' => $scheduledTime ? 'scheduled' : 'published',
                'published_at' => $scheduledTime ? null : now(),
            ]);
            return true;
        }

        return $result['error']['message'] ?? $result['error'] ?? false;
    }

    public function editPost($id)
    {
        $post = MarketingPost::findOrFail($id);
        
        if ($post->account->created_by != Auth::id() && $post->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $user = Auth::user();
        $accounts = MarketingAccount::where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                      ->orWhere('created_by', $user->created_by ?: $user->id);
            })->get();

        $campaigns = MarketingCampaign::whereIn('account_id', $accounts->pluck('id'))
            ->where('status', 'active')
            ->get();

        return view('marketing.posts.edit', compact('post', 'accounts', 'campaigns'));
    }

    public function updatePost(Request $request, $id)
    {
        $post = MarketingPost::findOrFail($id);
        
        if ($post->account->created_by != Auth::id() && $post->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'scheduled_at' => 'nullable|date',
            'media_url' => 'nullable|string',
            'media_urls' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $scheduledTime = $request->scheduled_at ? strtotime($request->scheduled_at) : null;

        try {
            $processedMediaUrl = $this->processMediaUrl($request->media_url);
            $processedMediaUrls = [];
            if ($request->media_type === 'carousel' && is_array($request->media_urls)) {
                foreach ($request->media_urls as $url) {
                    if (!empty($url)) {
                        $processedMediaUrls[] = $this->processMediaUrl($url);
                    }
                }
            } elseif (isset($post->metadata['urls']) && is_array($post->metadata['urls'])) {
                $processedMediaUrls = [];
                foreach ($post->metadata['urls'] as $url) {
                    $processedMediaUrls[] = $this->processMediaUrl($url);
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        // If post is scheduled or published, we update it via Meta API
        if (in_array($post->status, ['scheduled', 'published']) && $post->post_id) {
            $currentMediaUrl = $processedMediaUrl ?: $post->media_url;

            if ($post->status === 'scheduled' && $scheduledTime) {
                // For scheduled posts: delete old and create new (Facebook doesn't allow updating scheduled_publish_time)
                $result = $this->metaApi->reschedulePost(
                    $post->post_id,
                    $post->account->page_id,
                    $post->account->access_token,
                    $request->content,
                    $scheduledTime,
                    $currentMediaUrl,
                    $post->media_type ?? 'image'
                );

                if (!$result || isset($result['error'])) {
                    $errorMsg = $result['error'] ?? __('Failed to reschedule post on Meta');
                    return redirect()->back()->with('error', $errorMsg);
                }

                // Update with new post_id from Meta
                $newPostId = $result['feed_post_id'] ?? $result['post_id'] ?? $result['id'] ?? $post->post_id;
                $post->update([
                    'post_id' => $newPostId,
                    'content' => $request->content,
                    'media_url' => $currentMediaUrl,
                    'metadata' => (count($processedMediaUrls) > 0) ? ['urls' => $processedMediaUrls] : $post->metadata,
                    'scheduled_at' => $request->scheduled_at,
                    'status' => 'scheduled',
                ]);

                return redirect()->route('marketing.posts')->with('success', __('Post rescheduled successfully'));
            } elseif ($post->status === 'published') {
                // For published posts: just update the message (no reschedule)
                $updated = $this->metaApi->updatePost(
                    $post->post_id,
                    $post->account->access_token,
                    $request->content,
                    null,
                    $post->media_type ?? 'image'
                );

                if (is_array($updated) && isset($updated['error'])) {
                    return redirect()->back()->with('error', __('Failed to update post on Meta: ') . $updated['error']);
                }
                if ($updated !== true) {
                    return redirect()->back()->with('error', __('Failed to update post on Meta'));
                }
            }
        }

        $post->update([
            'content' => $request->content,
            'media_url' => $processedMediaUrl ?: $post->media_url,
            'metadata' => (count($processedMediaUrls) > 0) ? ['urls' => $processedMediaUrls] : $post->metadata,
            'scheduled_at' => $request->scheduled_at,
            'status' => $post->status === 'published' ? 'published' : ($request->scheduled_at ? 'scheduled' : 'draft'),
        ]);

        return redirect()->route('marketing.posts')->with('success', __('Post updated successfully'));
    }

    public function deletePost($id)
    {
        $post = MarketingPost::findOrFail($id);
        
        if ($post->account->created_by != Auth::id() && $post->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        if ($post->post_id) {
            $this->metaApi->deletePost($post->post_id, $post->account->access_token);
        }

        $post->delete();

        return redirect()->back()->with('success', __('Post deleted successfully'));
    }

    public function syncPostInsights($id)
    {
        $post = MarketingPost::findOrFail($id);
        
        if ($post->account->created_by != Auth::id() && $post->account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        if (!$post->post_id || $post->status !== 'published') {
            return redirect()->back()->with('error', __('Post cannot be synced. It must be published and have a valid remote ID.'));
        }

        if ($post->account->platform === 'instagram') {
            $stats = $this->metaApi->getInstagramPostStats($post->post_id, $post->account->access_token);
        } else {
            $stats = $this->metaApi->getPostStats($post->post_id, $post->account->access_token);
        }

        if ($stats) {
            $post->update([
                'likes' => $stats['likes'] ?? 0,
                'comments' => $stats['comments'] ?? 0,
                'shares' => $stats['shares'] ?? 0,
            ]);
            return redirect()->back()->with('success', __('Post stats synced successfully'));
        }

        return redirect()->back()->with('error', __('Failed to sync post stats from Meta'));
    }

    private function processMediaUrl($url)
    {
        if (empty($url)) return $url;

        // Clean mobile share URLs: remove drivesdk param, normalize the URL
        $url = preg_replace('/[?&]usp=drivesdk/', '', $url);
        $url = preg_replace('/[?&]usp=sharing/', '', $url);
        $url = rtrim($url, '?&');

        if (preg_match('#drive\.google\.com/(?:file/d/|open\?id=)([a-zA-Z0-9_-]+)#', $url, $matches)) {
            $fileId = $matches[1];

            \Log::info("GDrive: Processing file ID: {$fileId}");

            // Try Service Account first (for private/Shared Drive files)
            $saEmail = config('services.google_drive.sa_client_email') ?: ($_ENV['GOOGLE_SA_CLIENT_EMAIL'] ?? null);
            $saKey   = config('services.google_drive.sa_private_key') ?: ($_ENV['GOOGLE_SA_PRIVATE_KEY'] ?? null);

            if ($saEmail && $saKey) {
                \Log::info("GDrive: Using Service Account: {$saEmail}");
                try {
                    $content = $this->downloadDriveFileWithServiceAccount($fileId, $saEmail, $saKey);
                    if ($content) {
                        return $this->saveDriveFile($content['body'], $content['type'], $fileId);
                    }
                } catch (\Exception $e) {
                    \Log::warning('GDrive SA failed, falling back to public: ' . $e->getMessage());
                }
            } else {
                \Log::warning('GDrive: No Service Account credentials found — config: ' . ($saEmail ? 'email OK' : 'email MISSING') . ', ' . ($saKey ? 'key OK' : 'key MISSING'));
            }

            // Fallback: public download (multiple methods)
            $urlsToTry = [
                "https://drive.usercontent.google.com/download?id={$fileId}&export=download&authuser=0&confirm=t",
                "https://drive.google.com/uc?export=download&id={$fileId}&confirm=t",
                "https://lh3.googleusercontent.com/d/{$fileId}",
            ];

            foreach ($urlsToTry as $downloadUrl) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(120)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        ])
                        ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                        ->get($downloadUrl);

                    if ($response->successful() && !str_contains($response->header('Content-Type') ?? '', 'text/html')) {
                        return $this->saveDriveFile($response->body(), $response->header('Content-Type'), $fileId);
                    }

                    // Handle Google virus scan confirmation page (large files)
                    if ($response->successful() && str_contains($response->body(), 'confirm=')) {
                        if (preg_match('/confirm=([a-zA-Z0-9_-]+)/', $response->body(), $confirmMatch)) {
                            $confirmUrl = "https://drive.google.com/uc?export=download&id={$fileId}&confirm={$confirmMatch[1]}";
                            $confirmResponse = \Illuminate\Support\Facades\Http::timeout(120)
                                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
                                ->withOptions(['allow_redirects' => true])
                                ->get($confirmUrl);

                            if ($confirmResponse->successful() && !str_contains($confirmResponse->header('Content-Type') ?? '', 'text/html')) {
                                return $this->saveDriveFile($confirmResponse->body(), $confirmResponse->header('Content-Type'), $fileId);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning("GDrive public download failed ({$downloadUrl}): " . $e->getMessage());
                    continue;
                }
            }

            throw new \Exception(
                __('Could not download the Google Drive file. Please make sure the file sharing is set to "Anyone with the link" can view.')
            );
        }

        return $url;
    }

    private function downloadDriveFileWithServiceAccount($fileId, $clientEmail, $privateKey)
    {
        // Step 1: Build JWT
        $now = time();
        $header  = $this->base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url_encode(json_encode([
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $signingInput = "$header.$payload";
        $privateKey   = str_replace('\\n', "\n", $privateKey);

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = "$signingInput." . $this->base64url_encode($signature);

        // Step 2: Exchange JWT for Access Token
        $tokenResponse = \Illuminate\Support\Facades\Http::post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if (!$tokenResponse->successful()) {
            throw new \Exception('Failed to get Google access token: ' . $tokenResponse->body());
        }

        $accessToken = $tokenResponse->json()['access_token'] ?? null;
        if (!$accessToken) {
            throw new \Exception('No access token returned from Google');
        }

        // Step 3: Get file metadata to check content type (supportsAllDrives for Shared Drive files)
        $metaResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}?fields=mimeType,name&supportsAllDrives=true&includeItemsFromAllDrives=true");

        $mimeType = $metaResponse->json()['mimeType'] ?? 'image/jpeg';

        // Step 4: Download the file (supportsAllDrives for Shared Drive files)
        $fileResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->timeout(120)
            ->withOptions(['allow_redirects' => true])
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media&supportsAllDrives=true");

        if (!$fileResponse->successful()) {
            throw new \Exception('Drive API download failed: ' . $fileResponse->status());
        }

        \Log::info("GDrive SA: Successfully downloaded file {$fileId} via Service Account");

        return ['body' => $fileResponse->body(), 'type' => $mimeType];
    }

    private function saveDriveFile($content, $contentType, $fileId = '')
    {
        $extension = 'jpg';
        if (str_contains($contentType, 'video'))      $extension = 'mp4';
        elseif (str_contains($contentType, 'png'))    $extension = 'png';
        elseif (str_contains($contentType, 'gif'))    $extension = 'gif';
        elseif (str_contains($contentType, 'webp'))   $extension = 'webp';

        $filename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
        $savePath = base_path() . '/marketing_media';

        if (!file_exists($savePath)) {
            mkdir($savePath, 0755, true);
        }

        file_put_contents($savePath . '/' . $filename, $content);
        $finalUrl = url('marketing_media/' . $filename);
        \Log::info('GDrive: Saved to: ' . $finalUrl);

        return $finalUrl;
    }

    /**
     * URL-safe Base64 encode (required for JWT building).
     * Replaces +/ with -_ and strips trailing = padding.
     */
    private function base64url_encode($data): string
    {
        return rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($data)), '=');
    }



    // ──────────────────────────────────────────────
    // Multi-Post Feature
    // ──────────────────────────────────────────────

    public function multiPost()
    {
        $user = Auth::user();

        $accounts = MarketingAccount::where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                      ->orWhere('created_by', $user->created_by ?: $user->id);
            })
            ->orderBy('platform')
            ->get();

        return view('marketing.posts.multi', compact('accounts'));
    }

    public function storeMultiPost(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_ids'  => 'required|array|min:1',
            'account_ids.*' => 'exists:marketing_accounts,id',
            'content'      => 'required|string|max:5000',
            'media_url'    => 'nullable|string',
            'media_type'   => 'nullable|in:image,video',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = Auth::user();
        $batchId = Str::uuid()->toString();
        $scheduledTimestamp = $request->scheduled_at ? strtotime($request->scheduled_at) : null;

        // Process media URL (Google Drive, etc.)
        try {
            $processedMediaUrl = $this->processMediaUrl($request->media_url);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        // Verify all account_ids belong to the user
        $accounts = MarketingAccount::whereIn('id', $request->account_ids)
            ->where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                      ->orWhere('created_by', $user->created_by ?: $user->id);
            })
            ->get();

        if ($accounts->isEmpty()) {
            return redirect()->back()->with('error', __('No valid accounts selected'))->withInput();
        }

        // Create a log entry + dispatch a job for each selected account
        foreach ($accounts as $account) {
            $log = MultiPostLog::create([
                'batch_id'   => $batchId,
                'created_by' => $user->id,
                'account_id' => $account->id,
                'platform'   => $account->platform,
                'status'     => 'pending',
            ]);

            PublishToSocialPlatform::dispatch(
                $log->id,
                $request->content,
                $processedMediaUrl,
                $request->media_type ?? 'image',
                $scheduledTimestamp,
            );
        }

        return redirect()->route('marketing.multipost.status', $batchId)
            ->with('success', __('Multi-post dispatched to :count platform(s)', ['count' => $accounts->count()]));
    }

    public function multiPostStatus($batchId)
    {
        $logs = MultiPostLog::where('batch_id', $batchId)
            ->with('account')
            ->get();

        if ($logs->isEmpty()) {
            abort(404);
        }

        // Check authorization
        $user = Auth::user();
        $firstLog = $logs->first();
        if ($firstLog->created_by != $user->id && $firstLog->created_by != ($user->created_by ?: $user->id)) {
            abort(403);
        }

        $summary = [
            'total'    => $logs->count(),
            'success'  => $logs->where('status', 'success')->count(),
            'failed'   => $logs->where('status', 'failed')->count(),
            'pending'  => $logs->whereIn('status', ['pending', 'publishing'])->count(),
        ];

        return view('marketing.posts.multi_status', compact('logs', 'batchId', 'summary'));
    }

    public function multiPostStatusJson($batchId)
    {
        $logs = MultiPostLog::where('batch_id', $batchId)
            ->with('account:id,account_name,platform')
            ->get(['id', 'account_id', 'platform', 'status', 'error_message', 'published_at']);

        return response()->json([
            'logs'    => $logs,
            'summary' => [
                'total'   => $logs->count(),
                'success' => $logs->where('status', 'success')->count(),
                'failed'  => $logs->where('status', 'failed')->count(),
                'pending' => $logs->whereIn('status', ['pending', 'publishing'])->count(),
            ],
        ]);
    }

    public function syncAccountInsights($id)
    {
        $account = MarketingAccount::findOrFail($id);

        if ($account->created_by != Auth::id() && $account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        if (!$account->access_token || !$account->page_id) {
            return redirect()->back()->with('error', __('Account has no access token or page ID'));
        }

        if ($account->platform === 'instagram') {
            $data = $this->metaApi->getInstagramInsights($account->page_id, $account->access_token);
        } elseif ($account->platform === 'facebook') {
            $data = $this->metaApi->getPageInsightsSummary($account->page_id, $account->access_token);
        } else {
            return redirect()->back()->with('error', __('Insights sync only supported for Facebook and Instagram'));
        }

        if (!$data) {
            return redirect()->back()->with('error', __('Failed to fetch insights from Meta API'));
        }

        \App\Models\MarketingInsight::updateOrCreate(
            ['account_id' => $account->id, 'date' => now()->toDateString()],
            [
                'followers'        => $data['followers'] ?? 0,
                'page_views'       => $data['page_views'] ?? 0,
                'profile_visits'   => $data['profile_views'] ?? $data['page_views'] ?? 0,
                'total_engagement' => $data['engagement'] ?? 0,
                'engagement_rate'  => ($data['followers'] > 0 && $data['engagement'] > 0)
                    ? round(($data['engagement'] / $data['followers']) * 100, 4)
                    : 0,
                'metadata' => [
                    'reach'       => $data['reach'] ?? 0,
                    'impressions' => $data['impressions'] ?? 0,
                    'video_views' => $data['video_views'] ?? 0,
                ],
            ]
        );

        return redirect()->back()->with('success', __('Insights synced for :name', ['name' => $account->account_name]));
    }

    public function analytics()
    {
        $user = Auth::user();
        
        $accounts = MarketingAccount::where('created_by', $user->id)
            ->orWhere('created_by', $user->created_by ?: $user->id)
            ->with(['campaigns', 'insights'])
            ->get();

        // Resolve the reporting window (preset days or custom from/to).
        $w = $this->resolveAnalyticsWindow();

        // NOTE: Live Meta stats are intentionally NOT fetched here. With many accounts
        // (some with hundreds of posts) a synchronous fetch blocks the request and times
        // out. Instead each card loads its own stats via AJAX -> analyticsAccountStats().

        $insights = MarketingInsight::whereIn('account_id', $accounts->pluck('id'))
            ->whereBetween('date', [$w['startDate']->toDateString(), $w['endDate']->toDateString()])
            ->orderBy('date', 'desc')
            ->get();

        $campaignResults = MarketingCampaignResult::whereIn('campaign_id', function($query) use ($accounts) {
            $query->select('id')
                ->from('marketing_campaigns')
                ->whereIn('account_id', $accounts->pluck('id'));
        })
        ->whereBetween('date', [$w['startDate']->toDateString(), $w['endDate']->toDateString()])
        ->orderBy('date', 'desc')
        ->get();

        return view('marketing.analytics', compact('accounts', 'insights', 'campaignResults')
            + [
                'dateRange'  => $w['dateRange'],
                'periodDays' => $w['periodDays'],
                'isCustom'   => $w['isCustom'],
                'isRealtime' => $w['followsAsTotal'],
                'rangeLabel' => $w['rangeLabel'],
                'fromValue'  => $w['fromValue'],
                'toValue'    => $w['toValue'],
            ]);
    }

    /**
     * Resolve the analytics reporting window from the request.
     * Modes: custom (?from&?to) or preset (?range=7|30|90, default 30).
     */
    private function resolveAnalyticsWindow(): array
    {
        $from = request('from');
        $to   = request('to');

        $followsAsTotal = false;

        if ($from && $to) {
            try {
                $startDate = Carbon::parse($from)->startOfDay();
                $endDate   = Carbon::parse($to)->endOfDay();
            } catch (\Exception $e) {
                $startDate = Carbon::now()->subDays(30)->startOfDay();
                $endDate   = Carbon::now();
            }
            if ($endDate->lt($startDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
            }
            if ($endDate->gt(Carbon::now())) {
                $endDate = Carbon::now();
            }
            $isCustom   = true;
            $dateRange  = $startDate->diffInDays($endDate) + 1;
            $periodDays = max(1, $dateRange);
            $rangeLabel = $startDate->format('M d, Y') . ' – ' . $endDate->format('M d, Y');
        } elseif (request('range') === 'today') {
            // Real-time = CURRENT totals. Page Follows shows the live total follower count
            // (followsAsTotal), and the period-based metrics are computed over the last 30
            // days because Meta has no "all-time total" for reach/engagement/etc.
            $startDate      = Carbon::now()->subDays(30)->startOfDay();
            $endDate        = Carbon::now();
            $isCustom       = false;
            $dateRange      = 'today';
            $periodDays     = 30;
            $followsAsTotal = true;
            $rangeLabel     = __('Live totals');
        } else {
            $dateRange = (int) request('range', 30);
            if (!in_array($dateRange, [7, 30, 90])) {
                $dateRange = 30;
            }
            $startDate  = Carbon::now()->subDays($dateRange)->startOfDay();
            $endDate    = Carbon::now();
            $isCustom   = false;
            $periodDays = $dateRange;
            $rangeLabel = __('Last') . ' ' . $dateRange . ' ' . __('Days');
        }

        return [
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'sinceTs'        => $startDate->timestamp,
            'untilTs'        => $endDate->timestamp,
            'dateRange'      => $dateRange,
            'periodDays'     => $periodDays,
            'isCustom'       => $isCustom,
            'followsAsTotal' => $followsAsTotal,
            'rangeLabel'     => $rangeLabel,
            'fromValue'      => $startDate->format('Y-m-d'),
            'toValue'        => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * AJAX endpoint: live Meta stats for a single account over the selected window.
     * Cached 10 minutes per account+window to avoid repeated Graph API calls.
     */
    public function analyticsAccountStats($id)
    {
        $user    = Auth::user();
        $account = MarketingAccount::findOrFail($id);

        // Ownership check (same logic as syncAccountInsights)
        if ($account->created_by != $user->id && $account->created_by != ($user->created_by ?: $user->id)) {
            abort(403);
        }

        if (!in_array($account->platform, ['facebook', 'instagram'])) {
            return response()->json(['error' => 'Unsupported platform'], 422);
        }
        if (!$account->access_token || !$account->page_id) {
            return response()->json(['error' => 'Account has no access token or page ID'], 422);
        }

        $w = $this->resolveAnalyticsWindow();
        $sinceTs = $w['sinceTs'];
        $untilTs = $w['untilTs'];
        $followsAsTotal = $w['followsAsTotal'];

        // Real-time = ALL-TIME totals, served instantly from the nightly pre-computed
        // snapshot (marketing:compute-lifetime-totals). If it hasn't run yet we fall
        // through to the live (last-30-days) path so the card still shows something.
        if ($followsAsTotal) {
            $lifetime = is_array($account->metadata) ? ($account->metadata['lifetime'] ?? null) : null;
            if (is_array($lifetime) && isset($lifetime['follows'])) {
                return response()->json(['stats' => $lifetime]);
            }
        }

        $cacheKey = "mkt_live_stats_{$account->id}_{$sinceTs}_{$untilTs}_" . ($followsAsTotal ? 'tot' : 'per');
        $stats = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($account, $sinceTs, $untilTs, $followsAsTotal) {
            if ($account->platform === 'instagram') {
                return $this->metaApi->getInstagramInsights($account->page_id, $account->access_token, $sinceTs, $untilTs, $followsAsTotal);
            }
            return $this->metaApi->getPageInsightsSummary($account->page_id, $account->access_token, $sinceTs, $untilTs, $followsAsTotal);
        });

        if (!$stats || isset($stats['error'])) {
            // Don't cache a failed/expired-token result — let the next load retry.
            Cache::forget($cacheKey);
            return response()->json([
                'error' => is_array($stats) ? ($stats['error'] ?? 'Failed to fetch insights from Meta API') : 'Failed to fetch insights from Meta API',
            ], 502);
        }

        return response()->json(['stats' => $stats]);
    }

    /**
     * Redirect to Facebook OAuth dialog
     */
    public function facebookConnect()
    {
        $appId = config('services.meta.app_id');
        $redirectUri = route('marketing.facebook.callback');
        $scope = 'pages_show_list,pages_read_engagement,pages_manage_posts,instagram_basic,instagram_content_publish';
        
        $url = "https://www.facebook.com/v18.0/dialog/oauth?client_id={$appId}&redirect_uri=" . urlencode($redirectUri) . "&scope={$scope}&response_type=code";
        
        return redirect($url);
    }

    /**
     * Handle Facebook OAuth callback
     */
    public function facebookCallback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('marketing.accounts')->with('error', __('Facebook authorization was cancelled'));
        }

        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('marketing.accounts')->with('error', __('No authorization code received'));
        }

        // Exchange code for access token
        $tokenData = $this->metaApi->exchangeCodeForToken($code, route('marketing.facebook.callback'));
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            return redirect()->route('marketing.accounts')->with('error', __('Failed to get access token from Facebook'));
        }

        $shortToken = $tokenData['access_token'];

        // Get long-lived token
        $longTokenData = $this->metaApi->getLongLivedToken($shortToken);
        $accessToken = $longTokenData['access_token'] ?? $shortToken;
        $expiresIn = $longTokenData['expires_in'] ?? null;

        // Get all pages
        $pages = $this->metaApi->getPages($accessToken);
        
        $addedCount = 0;
        foreach ($pages as $page) {
            // Add Facebook page
            $exists = MarketingAccount::where('created_by', Auth::id())
                ->where('platform', 'facebook')
                ->where('page_id', $page['id'])
                ->exists();
            
            if (!$exists) {
                MarketingAccount::create([
                    'created_by' => Auth::id(),
                    'platform' => 'facebook',
                    'account_name' => $page['name'],
                    'account_id' => $page['id'],
                    'access_token' => $page['access_token'] ?? $accessToken,
                    'page_id' => $page['id'],
                    'page_name' => $page['name'],
                    'status' => 'active',
                    'token_expires_at' => $expiresIn ? Carbon::now()->addSeconds($expiresIn) : null,
                ]);
                $addedCount++;
            }

            // Check for Instagram business account linked to this page
            $igData = $this->metaApi->getInstagramAccounts($page['id'], $page['access_token'] ?? $accessToken);
            if ($igData && isset($igData['instagram_business_account'])) {
                $igAccountId = $igData['instagram_business_account']['id'];
                
                // Get Instagram account info
                try {
                    $igInfo = Http::get("https://graph.facebook.com/v18.0/{$igAccountId}", [
                        'fields' => 'username,name,profile_picture_url',
                        'access_token' => $page['access_token'] ?? $accessToken,
                    ]);
                    
                    $igName = $igInfo->successful() ? ($igInfo->json()['username'] ?? $igInfo->json()['name'] ?? $page['name'] . ' (Instagram)') : $page['name'] . ' (Instagram)';
                } catch (\Exception $e) {
                    $igName = $page['name'] . ' (Instagram)';
                }

                $igExists = MarketingAccount::where('created_by', Auth::id())
                    ->where('platform', 'instagram')
                    ->where('account_id', $igAccountId)
                    ->exists();
                
                if (!$igExists) {
                    MarketingAccount::create([
                        'created_by' => Auth::id(),
                        'platform' => 'instagram',
                        'account_name' => $igName,
                        'account_id' => $igAccountId,
                        'access_token' => $page['access_token'] ?? $accessToken,
                        'page_id' => $igAccountId,
                        'page_name' => $igName,
                        'status' => 'active',
                        'token_expires_at' => $expiresIn ? Carbon::now()->addSeconds($expiresIn) : null,
                    ]);
                    $addedCount++;
                }
            }
        }

        if ($addedCount > 0) {
            return redirect()->route('marketing.accounts')->with('success', __(':count account(s) connected via Facebook', ['count' => $addedCount]));
        }

        return redirect()->route('marketing.accounts')->with('info', __('All Facebook pages are already connected'));
    }

    /**
     * Verify an access token via Meta API
     */
    public function verifyAccount($id)
    {
        $account = MarketingAccount::findOrFail($id);
        
        if ($account->created_by != Auth::id() && $account->created_by != Auth::user()->created_by) {
            abort(403);
        }

        if (!$account->access_token) {
            return redirect()->back()->with('error', __('No access token to verify'));
        }

        try {
            if (in_array($account->platform, ['facebook', 'instagram'])) {
                // Verify by calling the pages/me endpoint
                $response = Http::get('https://graph.facebook.com/v18.0/me', [
                    'access_token' => $account->access_token,
                ]);

                if ($response->successful()) {
                    $account->update(['status' => 'active']);
                    return redirect()->back()->with('success', __('Account verified successfully! Token is valid.'));
                } else {
                    $account->update(['status' => 'expired']);
                    $error = $response->json()['error']['message'] ?? 'Unknown error';
                    return redirect()->back()->with('error', __('Token verification failed: ') . $error);
                }
            }

            return redirect()->back()->with('info', __('Verification is only available for Facebook and Instagram accounts'));
        } catch (\Exception $e) {
            Log::error('Account verification error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Verification failed: ') . $e->getMessage());
        }
    }
}
