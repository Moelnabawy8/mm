<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealPurchase;
use App\Models\Installment;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\SalesMeeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * WebsiteAnalyticsController
 *
 * Serves analytics dashboards for Arabic/English WooCommerce websites.
 * Fetches data SERVER-SIDE from WooCommerce REST API (keeps consumer keys
 * private — they never reach the browser) and exposes JSON endpoints for
 * the frontend dashboard to consume via same-origin AJAX.
 */
class WebsiteAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Every action in this controller requires the "show websites dashboard"
        // permission. Users/roles without it get a 403 when hitting any URL
        // under /websites/* including the JSON data endpoint.
        $this->middleware(function ($request, $next) {
            if (!\Auth::check() || !\Auth::user()->canViewWebsitesDashboard()) {
                abort(403, 'You do not have permission to view the Websites dashboard.');
            }
            return $next($request);
        });
    }

    private function siteConfig(string $site): array
    {
        if ($site === 'arabic') {
            return [
                'site_key'        => 'arabic',
                'site_name'       => 'Arabic Website',
                'site_url'        => 'https://my-communication.com',
                'api_base'        => 'https://my-communication.com/wp-json/wc/v3',
                'consumer_key'    => env('WOOCOMMERCE_AR_CONSUMER_KEY', ''),
                'consumer_secret' => env('WOOCOMMERCE_AR_CONSUMER_SECRET', ''),
                'currency'        => 'USD',
                'locale'          => 'en',
                'direction'       => 'ltr',
            ];
        }
        return [
            'site_key'        => 'english',
            'site_name'       => 'English Website',
            'site_url'        => 'https://my-communication.com',
            'api_base'        => 'https://my-communication.com/wp-json/wc/v3',
            'consumer_key'    => env('WOOCOMMERCE_EN_CONSUMER_KEY', ''),
            'consumer_secret' => env('WOOCOMMERCE_EN_CONSUMER_SECRET', ''),
            'currency'        => 'SAR',
            'locale'          => 'en',
            'direction'       => 'ltr',
        ];
    }

    public function arabic()
    {
        $config = $this->siteConfig('arabic');
        // Never expose consumer keys to the browser
        $viewConfig = collect($config)->except(['consumer_key', 'consumer_secret'])->toArray();
        return view('websites.analytics', ['config' => $viewConfig]);
    }

    public function english()
    {
        $config = $this->siteConfig('english');
        $viewConfig = collect($config)->except(['consumer_key', 'consumer_secret'])->toArray();
        return view('websites.analytics', ['config' => $viewConfig]);
    }

    /**
     * JSON endpoint: fetches WooCommerce data server-side and returns
     * a combined payload for the dashboard. Cached 60s to protect the
     * upstream WooCommerce site and keep the dashboard snappy.
     */
    public function data(Request $request, string $site)
    {
        if (!in_array($site, ['arabic', 'english'])) {
            return response()->json(['error' => 'Invalid site'], 400);
        }
        $config = $this->siteConfig($site);

        if (empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return response()->json([
                'error'    => 'API keys not configured',
                'fallback' => true,
            ], 200);
        }

        // Fetching thousands of WooCommerce records can blow the default 128M
        // limit. Raise just for this request — the trimmed payload stays small.
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $data = $this->fetchAll($config);
        return response()->json($data);
    }

    private function fetchAll(array $config): array
    {
        $auth = [
            'consumer_key'    => $config['consumer_key'],
            'consumer_secret' => $config['consumer_secret'],
        ];

        // Fetch ALL orders (trimmed) and products via pagination.
        $ordersResult    = $this->wcGetAll($config['api_base'] . '/orders',    $auth, ['orderby' => 'date', 'order' => 'desc'], 'order');
        $productsResult  = $this->wcGetAll($config['api_base'] . '/products',  $auth, ['orderby' => 'popularity'], 'product');

        // Customers: the dashboard only needs the TOTAL and a "new this month"
        // split — not every customer record. Fetching 8000+ customers takes
        // ~90s and is pointless. Use X-WP-Total header (one cheap request) +
        // a date-filtered count for the "new" bucket.
        $customersResult = $this->countCustomers($config['api_base'] . '/customers', $auth);

        $reportsSales = $this->wcGet($config['api_base'] . '/reports/sales', array_merge($auth, ['period' => 'year']));

        return [
            'error'      => null,
            'fallback'   => false,
            'orders'     => $ordersResult['items'],
            'products'   => $productsResult['items'],
            'customers'  => $customersResult['items'],  // empty — we don't need per-row detail
            'report'     => is_array($reportsSales) ? ($reportsSales[0] ?? null) : null,
            'fetched_at' => now()->toIso8601String(),
            'totals'     => [
                'orders'        => $ordersResult['total'],
                'products'      => $productsResult['total'],
                'customers'     => $customersResult['total'],
                'new_customers' => $customersResult['new'] ?? 0,
            ],
        ];
    }

    /**
     * Fetch pages from a WooCommerce list endpoint — PARALLEL pagination.
     * 1. Grab page 1 sequentially (needed to read X-WP-TotalPages header).
     * 2. Fire pages 2..N concurrently via Http::pool() — cuts wall time from
     *    (N * perRequest) to ~max(perRequest) + overhead.
     * Each record is trimmed on the fly via trimRecord() to keep memory small.
     * Returns ['items' => trimmed array, 'total' => true store count].
     */
    private function wcGetAll(string $url, array $auth, array $extraParams, string $type): array
    {
        $items = [];
        $perPage = 100;
        $maxPages = 100;   // safety cap
        $trueTotal = 0;
        $totalPages = 1;

        // Page 1 — we need its headers to know how many pages exist
        try {
            $first = Http::timeout(45)->get($url, array_merge($auth, $extraParams, [
                'per_page' => $perPage, 'page' => 1,
            ]));
            if (!$first->successful()) {
                \Log::warning('WooCommerce pagination first page error', [
                    'url' => $url, 'status' => $first->status(),
                ]);
                return ['items' => [], 'total' => 0];
            }
            $trueTotal  = (int) ($first->header('X-WP-Total') ?: 0);
            $totalPages = min($maxPages, (int) ($first->header('X-WP-TotalPages') ?: 1));

            foreach (($first->json() ?: []) as $record) {
                $items[] = $this->trimRecord($record, $type);
            }
        } catch (\Exception $e) {
            \Log::error('WooCommerce first page exception: ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }

        // Pages 2..N in parallel — chunk to avoid opening 100 sockets at once
        if ($totalPages > 1) {
            $chunkSize = 20; // 20 concurrent requests per batch — saturates the upstream nicely
            for ($batchStart = 2; $batchStart <= $totalPages; $batchStart += $chunkSize) {
                $batchEnd = min($batchStart + $chunkSize - 1, $totalPages);

                $responses = Http::pool(function ($pool) use ($url, $auth, $extraParams, $perPage, $batchStart, $batchEnd) {
                    $out = [];
                    for ($p = $batchStart; $p <= $batchEnd; $p++) {
                        $out[] = $pool->as('p' . $p)->timeout(45)->get($url, array_merge($auth, $extraParams, [
                            'per_page' => $perPage, 'page' => $p,
                        ]));
                    }
                    return $out;
                });

                for ($p = $batchStart; $p <= $batchEnd; $p++) {
                    $resp = $responses['p' . $p] ?? null;
                    if (!$resp || !method_exists($resp, 'successful') || !$resp->successful()) continue;
                    foreach (($resp->json() ?: []) as $record) {
                        $items[] = $this->trimRecord($record, $type);
                    }
                }
            }
        }

        return [
            'items' => $items,
            'total' => $trueTotal ?: count($items),
        ];
    }

    /**
     * Cheap customer counts via X-WP-Total headers — no body parsing needed.
     * Returns ['items' => [], 'total' => N] for compatibility with wcGetAll().
     * Also tries to count "new" customers registered in the last 30 days.
     */
    private function countCustomers(string $url, array $auth): array
    {
        $total = 0;
        $newLastMonth = 0;
        try {
            // Grab total via X-WP-Total header AND the newest 100 customers in parallel.
            // `after` isn't reliably supported on /customers, so we fetch the 100
            // most-recent registrations and count those within the last 30 days
            // client-side — far more accurate than a percentage guess.
            $responses = Http::pool(fn ($pool) => [
                $pool->as('total')->timeout(20)->get($url, array_merge($auth, ['per_page' => 1])),
                $pool->as('recent')->timeout(20)->get($url, array_merge($auth, [
                    'per_page' => 100,
                    'orderby'  => 'registered_date',
                    'order'    => 'desc',
                ])),
            ]);

            if (isset($responses['total']) && method_exists($responses['total'], 'successful') && $responses['total']->successful()) {
                $total = (int) ($responses['total']->header('X-WP-Total') ?: 0);
            }
            if (isset($responses['recent']) && method_exists($responses['recent'], 'successful') && $responses['recent']->successful()) {
                $recent = $responses['recent']->json() ?: [];
                $threshold = now()->subDays(30)->timestamp;
                foreach ($recent as $c) {
                    $ts = isset($c['date_created']) ? strtotime($c['date_created']) : 0;
                    if ($ts && $ts >= $threshold) $newLastMonth++;
                }
                // If all 100 newest are within the window, there are likely more we
                // didn't see — report at least 100 (slight under-count is better
                // than silently dropping the signal).
            }
        } catch (\Exception $e) {
            \Log::error('countCustomers exception: ' . $e->getMessage());
        }
        return [
            'items' => [],
            'total' => $total,
            'new'   => min($newLastMonth, $total),
        ];
    }

    /**
     * Keep only the fields the dashboard actually renders. A full WooCommerce
     * order JSON can be ~10 KB; trimmed version is ~200 bytes. 10x–50x smaller.
     */
    private function trimRecord($r, string $type): array
    {
        if ($type === 'order') {
            return [
                'id'           => $r['id'] ?? null,
                'status'       => $r['status'] ?? '',
                'total'        => $r['total'] ?? '0',
                'currency'     => $r['currency'] ?? '',
                'date_created' => $r['date_created'] ?? null,
                'billing'      => [
                    'first_name' => $r['billing']['first_name'] ?? '',
                    'last_name'  => $r['billing']['last_name']  ?? '',
                ],
            ];
        }
        if ($type === 'product') {
            return [
                'id'          => $r['id'] ?? null,
                'name'        => $r['name'] ?? '',
                'price'       => $r['price'] ?? '0',
                'total_sales' => $r['total_sales'] ?? 0,
            ];
        }
        if ($type === 'customer') {
            return [
                'id'           => $r['id'] ?? null,
                'date_created' => $r['date_created'] ?? null,
            ];
        }
        return $r;
    }

    /**
     * JSON endpoint for the CRM tab on the Websites dashboard.
     * Returns counters + recent rows from Leads / Deals / DealPurchases /
     * Installments / SalesMeetings — all scoped to the current tenant
     * (created_by = Auth::user()->creatorId()) following the same pattern
     * used everywhere else in the app.
     *
     * Site param is accepted for URL symmetry with the WooCommerce endpoint
     * but does NOT filter the data (user chose option A — no website filter).
     */
    public function crmData(Request $request, string $site)
    {
        if (!in_array($site, ['arabic', 'english'])) {
            return response()->json(['error' => 'Invalid site'], 400);
        }

        $usr        = Auth::user();
        $tenantId   = $usr->creatorId();
        $startMonth = now()->startOfMonth();
        $today        = now()->startOfDay();
        $monthAgo     = now()->subDays(30);
        $startOfWeek  = now()->startOfWeek();
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();

        // Pipelines list for the dropdown — always returned so the frontend
        // can render the selector on first paint.
        $pipelines = Pipeline::where('created_by', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Optional pipeline filter: ?pipeline=ID (or "all" / empty for tenant-wide)
        $pipelineId = null;
        $pipelineParam = $request->query('pipeline');
        if ($pipelineParam !== null && $pipelineParam !== '' && $pipelineParam !== 'all') {
            $pipelineId = (int) $pipelineParam;
            // Validate the pipeline belongs to this tenant — silently ignore otherwise
            if (!$pipelines->contains('id', $pipelineId)) {
                $pipelineId = null;
            }
        }

        // ---- Leads ---- (Leads have pipeline_id directly)
        $leadsBase = Lead::where('created_by', $tenantId);
        if ($pipelineId) {
            $leadsBase->where('pipeline_id', $pipelineId);
        }

        $leadsTotal     = (clone $leadsBase)->count();
        $leadsThisMonth = (clone $leadsBase)->where('created_at', '>=', $startMonth)->count();
        $recentLeads    = (clone $leadsBase)
            ->with(['stage:id,name', 'assignedUser:id,name'])
            ->orderByRaw('CAST(COALESCE(date, created_at) AS DATETIME) DESC')
            ->limit(10)
            ->get(['id','name','email','phone','country','city','stage_id','user_id','date','created_at']);

        // ---- Deals ---- (Deals also have pipeline_id)
        $dealsBase = Deal::where('created_by', $tenantId);
        if ($pipelineId) {
            $dealsBase->where('pipeline_id', $pipelineId);
        }
        $dealStatusCounts = (clone $dealsBase)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();
        $dealsTotal  = array_sum($dealStatusCounts);
        $recentDeals = (clone $dealsBase)
            ->with(['lead:id,name', 'stage:id,name'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id','name','lead_id','status','stage_id','datetime','created_at']);

        // Deal-scope closure — applied to DealPurchases / Installments via the deal relation
        $dealScopeFn = function ($q) use ($tenantId, $pipelineId) {
            $q->where('created_by', $tenantId);
            if ($pipelineId) {
                $q->where('pipeline_id', $pipelineId);
            }
        };

        // ---- Deal Purchases ----
        $purchaseAgg = DealPurchase::whereHas('deal', $dealScopeFn)
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(price),0) AS total_price, COALESCE(SUM(paid_amount),0) AS total_paid')
            ->first();
        $purchasesTotal   = (int) ($purchaseAgg->cnt ?? 0);
        $purchasesPrice   = (float) ($purchaseAgg->total_price ?? 0);
        $purchasesPaid    = (float) ($purchaseAgg->total_paid ?? 0);
        $purchasesDue     = max(0, $purchasesPrice - $purchasesPaid);

        // ---- Installments ----
        $instBase = Installment::whereHas('dealPurchase.deal', $dealScopeFn);

        $instTotalCount     = (clone $instBase)->count();
        $instPaidCount      = (clone $instBase)->where('status', 'paid')->count();
        $instUnpaidCount    = (clone $instBase)->where('status', 'unpaid')->count();
        $instOverdueCount   = (clone $instBase)->where('status', 'unpaid')
                                              ->whereDate('date', '<', $today)->count();
        $instUnpaidAmount   = (float) (clone $instBase)->where('status', 'unpaid')
                                              ->sum(DB::raw('amount - COALESCE(exemption,0)'));
        $overdueInstallments = (clone $instBase)
            ->with(['dealPurchase.deal:id,name,lead_id', 'dealPurchase.deal.lead:id,name'])
            ->where('status', 'unpaid')
            ->whereDate('date', '<', $today)
            ->orderBy('date', 'asc')
            ->limit(10)
            ->get(['id','deal_purchase_id','amount','exemption','date','status','description']);

        // ---- Sales Meetings ----
        // Meetings can be linked to a lead and/or a deal. When a pipeline filter is
        // active we keep only meetings whose lead OR deal belong to that pipeline.
        $meetingsBase = SalesMeeting::where('created_by', $tenantId);
        if ($pipelineId) {
            $meetingsBase->where(function ($q) use ($pipelineId) {
                $q->whereHas('lead', fn($lq) => $lq->where('pipeline_id', $pipelineId))
                  ->orWhereHas('deal', fn($dq) => $dq->where('pipeline_id', $pipelineId));
            });
        }
        $meetingsTotalCount    = (clone $meetingsBase)->count();
        $upcomingMeetingsCount = (clone $meetingsBase)->whereDate('date', '>=', $today)->count();
        $last30dMeetings       = (clone $meetingsBase)->where('created_at', '>=', $monthAgo)->count();

        // Diagnostic: meetings that exist for this tenant but are EXCLUDED
        // by the current pipeline filter. Shown to the user so they can
        // investigate / clean up orphan or mis-tagged meetings.
        $excludedMeetings = collect();
        if ($pipelineId) {
            $excludedMeetings = SalesMeeting::where('created_by', $tenantId)
                ->where(function ($q) use ($pipelineId) {
                    $q->whereDoesntHave('lead', fn($lq) => $lq->where('pipeline_id', $pipelineId))
                      ->whereDoesntHave('deal', fn($dq) => $dq->where('pipeline_id', $pipelineId));
                })
                ->with(['lead:id,name,pipeline_id', 'deal:id,name,pipeline_id'])
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get(['id','title','lead_id','deal_id','date','time','created_at']);
        }
        $upcomingMeetings      = (clone $meetingsBase)
            ->with(['lead:id,name', 'deal:id,name', 'getMeetingType:id,name'])
            ->whereDate('date', '>=', $today)
            ->orderBy('date', 'asc')->orderBy('time', 'asc')
            ->limit(10)
            ->get(['id','title','lead_id','deal_id','meeting_type_id','date','time','duration','link']);

        // ---- Today's activity ----
        $leadsTodayCount    = (clone $leadsBase)->whereDate('created_at', now())->count();
        $dealsWonTodayCount = (clone $dealsBase)->where('status', 'Won')->whereDate('updated_at', now())->count();
        $meetingsTodayCount = (clone $meetingsBase)->whereDate('date', now())->count();
        $instDueTodayCount  = (clone $instBase)->where('status', 'unpaid')->whereDate('date', now())->count();
        $instDueTodayAmt    = (float) (clone $instBase)->where('status', 'unpaid')
                                ->whereDate('date', now())
                                ->sum(DB::raw('amount - COALESCE(exemption,0)'));

        // ---- This week ----
        $leadsThisWeek     = (clone $leadsBase)->where('created_at', '>=', $startOfWeek)->count();
        $dealsWonThisWeek  = (clone $dealsBase)->where('status', 'Won')->where('updated_at', '>=', $startOfWeek)->count();
        $purchasesThisWeek = (float) DealPurchase::whereHas('deal', $dealScopeFn)
                                ->where('created_at', '>=', $startOfWeek)->sum('price');

        // ---- Rates ----
        $wonCount       = (int) ($dealStatusCounts['Won'] ?? 0);
        $conversionRate = $leadsTotal > 0 ? round(($dealsTotal / $leadsTotal) * 100, 1) : 0;
        $winRate        = $dealsTotal > 0 ? round(($wonCount / $dealsTotal) * 100, 1) : 0;
        $avgDealSize    = $purchasesTotal > 0 ? round($purchasesPrice / $purchasesTotal, 2) : 0;

        // ---- Leads by stage ----
        $leadsByStage = DB::table('leads')
            ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')
            ->where('leads.created_by', $tenantId)
            ->when($pipelineId, fn($q) => $q->where('leads.pipeline_id', $pipelineId))
            ->selectRaw("COALESCE(lead_stages.name, 'No Stage') as stage_name, COUNT(*) as c")
            ->groupBy('lead_stages.name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(8)
            ->get()
            ->map(fn($r) => ['stage' => $r->stage_name, 'count' => (int) $r->c])
            ->values();

        // ---- Deals by stage ----
        $dealsByStage = DB::table('deals')
            ->leftJoin('stages', 'deals.stage_id', '=', 'stages.id')
            ->where('deals.created_by', $tenantId)
            ->when($pipelineId, fn($q) => $q->where('deals.pipeline_id', $pipelineId))
            ->selectRaw("COALESCE(stages.name, 'No Stage') as stage_name, COUNT(*) as c")
            ->groupBy('stages.name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(8)
            ->get()
            ->map(fn($r) => ['stage' => $r->stage_name, 'count' => (int) $r->c])
            ->values();

        // ---- Top performers (by deals won) ----
        $topPerformers = DB::table('deals')
            ->leftJoin('users', 'deals.added_by', '=', 'users.id')
            ->where('deals.created_by', $tenantId)
            ->when($pipelineId, fn($q) => $q->where('deals.pipeline_id', $pipelineId))
            ->selectRaw("COALESCE(users.name, 'Unassigned') as uname,
                         COUNT(*) as total_deals,
                         SUM(CASE WHEN deals.status = 'Won'  THEN 1 ELSE 0 END) as won_deals,
                         SUM(CASE WHEN deals.status = 'Loss' THEN 1 ELSE 0 END) as lost_deals")
            ->groupBy('users.id', 'users.name')
            ->orderBy('won_deals', 'desc')
            ->limit(8)
            ->get()
            ->map(fn($r) => [
                'name'  => $r->uname,
                'total' => (int) $r->total_deals,
                'won'   => (int) $r->won_deals,
                'lost'  => (int) $r->lost_deals,
            ])
            ->values();

        // ---- Monthly trend (last 6 months) ----
        $mlData = DB::table('leads')
            ->where('created_by', $tenantId)
            ->when($pipelineId, fn($q) => $q->where('pipeline_id', $pipelineId))
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as mo, COUNT(*) as c")
            ->groupBy('mo')->orderBy('mo')
            ->pluck('c', 'mo');

        $mdWon = DB::table('deals')
            ->where('created_by', $tenantId)
            ->when($pipelineId, fn($q) => $q->where('pipeline_id', $pipelineId))
            ->where('status', 'Won')
            ->where('updated_at', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(updated_at,'%Y-%m') as mo, COUNT(*) as c")
            ->groupBy('mo')->orderBy('mo')
            ->pluck('c', 'mo');

        $mdRev = DB::table('deal_purchases')
            ->join('deals', 'deal_purchases.deal_id', '=', 'deals.id')
            ->where('deals.created_by', $tenantId)
            ->when($pipelineId, fn($q) => $q->where('deals.pipeline_id', $pipelineId))
            ->where('deal_purchases.created_at', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(deal_purchases.created_at,'%Y-%m') as mo, COALESCE(SUM(deal_purchases.price),0) as rev")
            ->groupBy('mo')->orderBy('mo')
            ->pluck('rev', 'mo');

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $mo    = now()->subMonths($i)->format('Y-m');
            $label = now()->subMonths($i)->format('M Y');
            $monthlyTrend[] = [
                'month'   => $label,
                'leads'   => (int) ($mlData[$mo] ?? 0),
                'won'     => (int) ($mdWon[$mo] ?? 0),
                'revenue' => (float) ($mdRev[$mo] ?? 0),
            ];
        }

        // ---- Today's meetings list ----
        $todayMeetingsList = (clone $meetingsBase)
            ->with(['lead:id,name', 'deal:id,name', 'getMeetingType:id,name'])
            ->whereDate('date', now())
            ->orderBy('time', 'asc')
            ->get(['id', 'title', 'lead_id', 'deal_id', 'meeting_type_id', 'date', 'time', 'duration', 'link']);

        // ---- Recent deal purchases ----
        $recentPurchases = DealPurchase::whereHas('deal', $dealScopeFn)
            ->with(['deal:id,name,lead_id', 'deal.lead:id,name'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'deal_id', 'price', 'paid_amount', 'payment_method', 'payment_type', 'purchase_date', 'created_at']);

        return response()->json([
            'error' => null,
            'pipelines'         => $pipelines->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'selected_pipeline' => $pipelineId,
            'kpis'  => [
                'leads'          => [
                    'total'          => $leadsTotal,
                    'new_this_month' => $leadsThisMonth,
                ],
                'deals'          => [
                    'total'  => $dealsTotal,
                    'active' => (int) ($dealStatusCounts['Active'] ?? 0),
                    'won'    => (int) ($dealStatusCounts['Won'] ?? 0),
                    'loss'   => (int) ($dealStatusCounts['Loss'] ?? 0),
                ],
                'deal_purchases' => [
                    'count'   => $purchasesTotal,
                    'revenue' => $purchasesPrice,
                    'paid'    => $purchasesPaid,
                    'due'     => $purchasesDue,
                ],
                'installments'   => [
                    'total'         => $instTotalCount,
                    'paid'          => $instPaidCount,
                    'unpaid'        => $instUnpaidCount,
                    'overdue'       => $instOverdueCount,
                    'unpaid_amount' => $instUnpaidAmount,
                ],
                'sales_meetings' => [
                    'total'    => $meetingsTotalCount,
                    'upcoming' => $upcomingMeetingsCount,
                    'last_30d' => $last30dMeetings,
                ],
            ],
            'recent_leads' => $recentLeads->map(fn($l) => [
                'id'      => $l->id,
                'name'    => $l->name,
                'email'   => $l->email,
                'phone'   => $l->phone,
                'country' => $l->country,
                'city'    => $l->city,
                'stage'   => optional($l->stage)->name,
                'owner'   => optional($l->assignedUser)->name,
                'date'    => $l->date ?: optional($l->created_at)->toDateString(),
            ]),
            'recent_deals' => $recentDeals->map(fn($d) => [
                'id'        => $d->id,
                'name'      => $d->name,
                'lead'      => optional($d->lead)->name,
                'status'    => $d->status,
                'stage'     => optional($d->stage)->name,
                'datetime'  => $d->datetime ? (string) $d->datetime : optional($d->created_at)->toDateString(),
            ]),
            'upcoming_meetings' => $upcomingMeetings->map(fn($m) => [
                'id'       => $m->id,
                'title'    => $m->title,
                'lead'     => optional($m->lead)->name,
                'deal'     => optional($m->deal)->name,
                'type'     => optional($m->getMeetingType)->name,
                'date'     => $m->date,
                'time'     => $m->time,
                'duration' => $m->duration,
                'link'     => $m->link,
            ]),
            'excluded_meetings' => $excludedMeetings->map(function ($m) {
                $reason = null;
                if (!$m->lead_id && !$m->deal_id) {
                    $reason = 'Orphan (no lead and no deal)';
                } elseif ($m->lead_id && !$m->lead) {
                    $reason = 'Lead #' . $m->lead_id . ' missing';
                } elseif ($m->deal_id && !$m->deal) {
                    $reason = 'Deal #' . $m->deal_id . ' missing';
                } elseif ($m->lead && optional($m->deal)) {
                    $reason = 'Linked to a different pipeline';
                } elseif ($m->lead) {
                    $reason = 'Lead in different pipeline';
                } elseif ($m->deal) {
                    $reason = 'Deal in different pipeline';
                }
                return [
                    'id'         => $m->id,
                    'title'      => $m->title,
                    'lead_id'    => $m->lead_id,
                    'lead_name'  => optional($m->lead)->name,
                    'deal_id'    => $m->deal_id,
                    'deal_name'  => optional($m->deal)->name,
                    'date'       => $m->date,
                    'time'       => $m->time,
                    'reason'     => $reason,
                    'created_at' => optional($m->created_at)->toDateTimeString(),
                ];
            })->values(),
            'overdue_installments' => $overdueInstallments->map(fn($i) => [
                'id'          => $i->id,
                'amount'      => (float) $i->amount,
                'exemption'   => (float) $i->exemption,
                'effective'   => (float) ($i->amount - $i->exemption),
                'date'        => $i->date,
                'description' => $i->description,
                'deal'        => optional(optional($i->dealPurchase)->deal)->name,
                'lead'        => optional(optional(optional($i->dealPurchase)->deal)->lead)->name,
            ]),
            'today' => [
                'leads'           => $leadsTodayCount,
                'deals_won'       => $dealsWonTodayCount,
                'meetings'        => $meetingsTodayCount,
                'inst_due_count'  => $instDueTodayCount,
                'inst_due_amount' => $instDueTodayAmt,
            ],
            'this_week' => [
                'leads'     => $leadsThisWeek,
                'deals_won' => $dealsWonThisWeek,
                'revenue'   => $purchasesThisWeek,
            ],
            'rates' => [
                'conversion' => $conversionRate,
                'win_rate'   => $winRate,
                'avg_deal'   => $avgDealSize,
            ],
            'leads_by_stage'   => $leadsByStage,
            'deals_by_stage'   => $dealsByStage,
            'top_performers'   => $topPerformers,
            'monthly_trend'    => $monthlyTrend,
            'today_meetings'   => $todayMeetingsList->map(fn($m) => [
                'id'       => $m->id,
                'title'    => $m->title,
                'lead'     => optional($m->lead)->name,
                'deal'     => optional($m->deal)->name,
                'type'     => optional($m->getMeetingType)->name,
                'date'     => $m->date,
                'time'     => $m->time,
                'duration' => $m->duration,
                'link'     => $m->link,
            ])->values(),
            'recent_purchases' => $recentPurchases->map(fn($p) => [
                'id'             => $p->id,
                'deal'           => optional($p->deal)->name,
                'lead'           => optional(optional($p->deal)->lead)->name,
                'price'          => (float) $p->price,
                'paid'           => (float) $p->paid_amount,
                'due'            => max(0.0, (float) $p->price - (float) $p->paid_amount),
                'payment_method' => $p->payment_method,
                'payment_type'   => $p->payment_type,
                'date'           => $p->purchase_date ?: optional($p->created_at)->toDateString(),
            ])->values(),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    private function wcGet(string $url, array $params)
    {
        try {
            $response = Http::timeout(20)->get($url, $params);
            if ($response->successful()) {
                return $response->json();
            }
            \Log::warning('WooCommerce API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 300),
            ]);
            return null;
        } catch (\Exception $e) {
            \Log::error('WooCommerce API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * JSON endpoint for the LearnPress orders tab.
     *
     * Talks to the custom MU plugin (erp-bridge.php) deployed on the
     * WordPress site at /wp-json/erp/v1/dashboard. Authenticated with a
     * bearer token shared between WORDPRESS_LP_BRIDGE_TOKEN (.env on this
     * server) and ERP_BRIDGE_TOKEN (wp-config.php on the WP server).
     */
    public function learnPressData(Request $request)
    {
        $base  = rtrim((string) env('WORDPRESS_LP_BRIDGE_URL', ''), '/');
        $token = (string) env('WORDPRESS_LP_BRIDGE_TOKEN', '');

        if ($base === '' || $token === '') {
            return response()->json([
                'error' => 'LearnPress bridge not configured. Set WORDPRESS_LP_BRIDGE_URL and WORDPRESS_LP_BRIDGE_TOKEN in .env.',
                'configured' => false,
            ], 200);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'])
                ->get($base . '/dashboard');

            if (!$response->successful()) {
                \Log::warning('ERP Bridge: upstream error', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                return response()->json([
                    'error'  => 'Bridge returned HTTP ' . $response->status(),
                    'detail' => substr($response->body(), 0, 200),
                ], 200);
            }

            $payload = $response->json();
            $payload['configured'] = true;
            $payload['error']      = null;
            return response()->json($payload);
        } catch (\Exception $e) {
            \Log::error('ERP Bridge: exception ' . $e->getMessage());
            return response()->json([
                'error' => 'Bridge unreachable: ' . $e->getMessage(),
            ], 200);
        }
    }

    /**
     * JSON endpoint: course activations (completed orders × course rows) for
     * a given period — defaults to current month. Each row = one student
     * gaining access to one course, with the price actually paid for it.
     */
    public function learnPressActivations(Request $request)
    {
        $base  = rtrim((string) env('WORDPRESS_LP_BRIDGE_URL', ''), '/');
        $token = (string) env('WORDPRESS_LP_BRIDGE_TOKEN', '');

        if ($base === '' || $token === '') {
            return response()->json([
                'error'      => 'LearnPress bridge not configured.',
                'configured' => false,
            ], 200);
        }

        $params = array_filter([
            'period'    => $request->query('period', 'this_month'),
            'date_from' => $request->query('date_from'),
            'date_to'   => $request->query('date_to'),
            'page'      => $request->query('page'),
            'per_page'  => $request->query('per_page', 200),
            'q'         => $request->query('q'),
        ], fn($v) => $v !== null && $v !== '');

        try {
            $response = Http::timeout(45)
                ->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'])
                ->get($base . '/activations', $params);

            if (!$response->successful()) {
                \Log::warning('ERP Bridge activations: upstream error', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                return response()->json([
                    'error'  => 'Bridge returned HTTP ' . $response->status(),
                    'detail' => substr($response->body(), 0, 200),
                ], 200);
            }

            $payload = $response->json();
            $payload['configured'] = true;
            $payload['error']      = null;
            return response()->json($payload);
        } catch (\Exception $e) {
            \Log::error('ERP Bridge activations: exception ' . $e->getMessage());
            return response()->json(['error' => 'Bridge unreachable: ' . $e->getMessage()], 200);
        }
    }
}
