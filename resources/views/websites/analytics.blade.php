@extends('layouts.admin')

@push('css-page')
<style>
    .wa-wrap { --wa-primary: #1976d2; --wa-primary-soft: #e3f2fd; --wa-success: #2e7d32; --wa-warning: #ed6c02; --wa-danger: #d32f2f; }
    .wa-wrap[dir="rtl"] { text-align: right; }
    .wa-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
    .wa-title { font-size:22px; font-weight:700; color:#0f1f36; margin:0; }
    .wa-subtitle { color:#5b6b82; font-size:13px; margin-top:4px; }
    .wa-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:24px; }
    .wa-card { background:#fff; border:1px solid #e4eaf2; border-radius:12px; padding:18px 20px; box-shadow:0 1px 2px rgba(0,0,0,.03); transition:transform .15s, box-shadow .15s; }
    .wa-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(25,118,210,.08); }
    .wa-card-top { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
    .wa-icon { width:40px; height:40px; border-radius:10px; background:var(--wa-primary-soft); color:var(--wa-primary); display:flex; align-items:center; justify-content:center; font-size:18px; }
    .wa-label { color:#5b6b82; font-size:13px; font-weight:500; }
    .wa-value { font-size:26px; font-weight:700; color:#0f1f36; margin:0; }
    .wa-delta { font-size:12px; margin-top:4px; }
    .wa-delta-up { color:var(--wa-success); }
    .wa-delta-down { color:var(--wa-danger); }
    .wa-grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(380px,1fr)); gap:16px; margin-bottom:24px; }
    .wa-panel { background:#fff; border:1px solid #e4eaf2; border-radius:12px; padding:20px; }
    .wa-panel h3 { margin:0 0 16px; font-size:15px; font-weight:700; color:#0f1f36; display:flex; align-items:center; justify-content:space-between; }
    .wa-panel h3 .wa-badge { font-size:11px; font-weight:500; background:var(--wa-primary-soft); color:var(--wa-primary); padding:3px 8px; border-radius:999px; }
    .wa-chart-box { position:relative; height:280px; }
    .wa-table { width:100%; border-collapse:collapse; font-size:13px; }
    .wa-table th { text-align:left; background:#f6f9fc; color:#5b6b82; font-weight:600; padding:10px 12px; border-bottom:1px solid #e4eaf2; }
    .wa-wrap[dir="rtl"] .wa-table th { text-align:right; }
    .wa-table td { padding:12px; border-bottom:1px solid #f0f3f7; color:#2d3748; }
    .wa-table tr:last-child td { border-bottom:none; }
    .wa-status { padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; text-transform:uppercase; }
    .wa-status-completed { background:#e8f5e9; color:var(--wa-success); }
    .wa-status-pending { background:#fff4e5; color:var(--wa-warning); }
    .wa-status-cancelled { background:#fdecea; color:var(--wa-danger); }
    .wa-status-processing { background:#e3f2fd; color:var(--wa-primary); }
    .wa-funnel { display:flex; flex-direction:column; gap:10px; }
    .wa-funnel-row { background:#f6f9fc; border-radius:10px; padding:14px 18px; position:relative; overflow:hidden; }
    .wa-funnel-fill { position:absolute; inset:0; background:linear-gradient(90deg, var(--wa-primary-soft), rgba(227,242,253,.3)); z-index:0; }
    .wa-wrap[dir="rtl"] .wa-funnel-fill { background:linear-gradient(-90deg, var(--wa-primary-soft), rgba(227,242,253,.3)); }
    .wa-funnel-content { position:relative; z-index:1; display:flex; justify-content:space-between; align-items:center; font-size:13px; }
    .wa-funnel-label { font-weight:600; color:#0f1f36; }
    .wa-funnel-value { color:var(--wa-primary); font-weight:700; }
    .wa-funnel-rate { color:#5b6b82; font-size:12px; margin-top:2px; }
    .wa-loading { text-align:center; padding:40px; color:#5b6b82; font-size:13px; }
    .wa-error-banner { background:#fff4e5; color:#8a6100; border:1px solid #ffd699; padding:10px 14px; border-radius:8px; font-size:12px; margin-bottom:16px; display:none; }
    .wa-error-banner.show { display:block; }
    .wa-product-item { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f0f3f7; font-size:13px; }
    .wa-product-item:last-child { border-bottom:none; }
    .wa-product-name { color:#0f1f36; font-weight:500; }
    .wa-product-stats { display:flex; gap:14px; color:#5b6b82; font-size:12px; }
    .wa-product-stats b { color:var(--wa-primary); }
    .wa-refresh-btn { background:var(--wa-primary); color:#fff; border:none; padding:8px 16px; border-radius:8px; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .wa-refresh-btn:hover { background:#1565c0; }
    /* Live tracking */
    .wa-live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#2e7d32; margin-right:6px; animation:wa-pulse 1.5s ease-in-out infinite; }
    @@keyframes wa-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }
    .wa-activity-feed { max-height:420px; overflow-y:auto; }
    .wa-activity-item { display:flex; gap:12px; padding:12px 4px; border-bottom:1px solid #f0f3f7; font-size:13px; }
    .wa-activity-item:last-child { border-bottom:none; }
    .wa-activity-icon { width:32px; height:32px; border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .wa-activity-body { flex:1; min-width:0; }
    .wa-activity-text { color:#0f1f36; line-height:1.4; }
    .wa-activity-text b { color:var(--wa-primary); font-weight:600; }
    .wa-activity-time { color:#8a96a8; font-size:11px; margin-top:3px; }
    .wa-source-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; }
    .wa-source-label { display:flex; align-items:center; gap:8px; font-size:13px; color:#2d3748; }
    .wa-source-dot { width:10px; height:10px; border-radius:3px; }
    .wa-source-value { font-weight:600; color:#0f1f36; font-size:13px; }
    .wa-source-pct { color:#5b6b82; font-size:11px; margin-left:6px; }
    .wa-geo-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f0f3f7; font-size:13px; }
    .wa-geo-row:last-child { border-bottom:none; }
    .wa-geo-flag { width:22px; text-align:center; font-size:16px; }
    .wa-geo-bar { flex:1; height:6px; background:#f0f3f7; border-radius:3px; margin:0 12px; overflow:hidden; }
    .wa-geo-fill { height:100%; background:linear-gradient(90deg,#1976d2,#42a5f5); border-radius:3px; }
    .wa-page-table tr:hover td { background:#f9fbfd; }
    .wa-sparkline-cell { width:90px; }
</style>
@endpush

@section('content')
<div class="wa-wrap" dir="{{ $config['direction'] }}">

    <div class="wa-header">
        <div>
            <h2 class="wa-title">
                <i class="ti ti-world-www" style="color:var(--wa-primary); margin-right:8px;"></i>
                {{ $config['site_name'] }} {{ __('Analytics') }}
            </h2>
            <div class="wa-subtitle">
                {{ __('E-commerce performance overview') }} &middot;
                <a href="{{ $config['site_url'] }}" target="_blank" rel="noopener">{{ $config['site_url'] }}</a>
            </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <span id="waDataBadge" class="wa-badge" style="padding:6px 12px; font-size:12px;">Loading…</span>
            <button class="wa-refresh-btn" onclick="waLoadAll()">
                <i class="ti ti-refresh"></i> {{ __('Refresh') }}
            </button>
        </div>
    </div>

    <div id="waErrorBanner" class="wa-error-banner">
        <i class="ti ti-alert-triangle"></i>
        <span id="waErrorText"></span>
    </div>

    {{-- Date filter — applies to Revenue / Orders / AOV / Charts / Funnel / Abandoned / Top Courses --}}
    <div class="wa-panel" style="margin-bottom:16px; padding:14px 18px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <strong style="font-size:13px; color:#0f1f36;"><i class="ti ti-calendar-filter"></i> {{ __('Date range') }}:</strong>
                <select id="waDatePreset" class="form-select" style="padding:6px 10px; font-size:13px; border:1px solid #e4eaf2; border-radius:6px; min-width:160px;">
                    <option value="today">{{ __('Today') }}</option>
                    <option value="yesterday">{{ __('Yesterday') }}</option>
                    <option value="7d">{{ __('Last 7 days') }}</option>
                    <option value="30d" selected>{{ __('Last 30 days') }}</option>
                    <option value="90d">{{ __('Last 90 days') }}</option>
                    <option value="this_month">{{ __('This month') }}</option>
                    <option value="last_month">{{ __('Last month') }}</option>
                    <option value="this_year">{{ __('This year') }}</option>
                    <option value="all">{{ __('All time') }}</option>
                    <option value="custom">{{ __('Custom range…') }}</option>
                </select>
                <input type="date" id="waDateFrom" style="padding:6px 10px; font-size:13px; border:1px solid #e4eaf2; border-radius:6px; display:none;">
                <input type="date" id="waDateTo"   style="padding:6px 10px; font-size:13px; border:1px solid #e4eaf2; border-radius:6px; display:none;">
                <button type="button" id="waDateApply" class="wa-refresh-btn" style="display:none;">
                    <i class="ti ti-check"></i> {{ __('Apply') }}
                </button>
            </div>
            <span id="waDateSummary" style="font-size:12px; color:#5b6b82;">—</span>
        </div>
    </div>

    <div id="waDebug" style="background:#f0f9ff; border:1px solid #b3d9f2; padding:8px 12px; border-radius:6px; font-size:11px; color:#0f1f36; font-family:monospace; margin-bottom:16px;">Initializing…</div>

    {{-- 1. Sales Overview Cards --}}
    <div class="wa-cards" id="overview" style="scroll-margin-top:90px;">
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon"><i class="ti ti-currency-dollar"></i></div>
                <div class="wa-label">{{ __('Total Revenue') }}</div>
            </div>
            <p class="wa-value" id="waRevenue">—</p>
            <div class="wa-delta wa-delta-up" id="waRevenueDelta">&nbsp;</div>
        </div>
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#e8f5e9; color:var(--wa-success);"><i class="ti ti-shopping-cart"></i></div>
                <div class="wa-label">{{ __('Total Orders') }}</div>
            </div>
            <p class="wa-value" id="waOrders">—</p>
            <div class="wa-delta wa-delta-up" id="waOrdersDelta">&nbsp;</div>
        </div>
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#fff4e5; color:var(--wa-warning);"><i class="ti ti-receipt"></i></div>
                <div class="wa-label">{{ __('Avg. Order Value') }}</div>
            </div>
            <p class="wa-value" id="waAOV">—</p>
            <div class="wa-delta" id="waAOVDelta">&nbsp;</div>
        </div>
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#f3e5f5; color:#7b1fa2;"><i class="ti ti-percentage"></i></div>
                <div class="wa-label">{{ __('Conversion Rate') }}</div>
            </div>
            <p class="wa-value" id="waConversion">—</p>
            <div class="wa-delta" id="waConversionDelta">&nbsp;</div>
        </div>
    </div>

    {{-- Live Tracking Cards --}}
    <div class="wa-cards" id="tracking" style="scroll-margin-top:90px;">
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#e8f5e9; color:var(--wa-success);"><i class="ti ti-user-check"></i></div>
                <div class="wa-label"><span class="wa-live-dot"></span>{{ __('Live Visitors') }}</div>
            </div>
            <p class="wa-value" id="waLiveVisitors">—</p>
            <div class="wa-delta" id="waLiveCountries" style="color:#5b6b82;">&nbsp;</div>
        </div>
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#e3f2fd; color:var(--wa-primary);"><i class="ti ti-eye"></i></div>
                <div class="wa-label">{{ __("Today's Sessions") }}</div>
            </div>
            <p class="wa-value" id="waTodaySessions">—</p>
            <div class="wa-delta wa-delta-up" id="waSessionsDelta">&nbsp;</div>
        </div>
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#fff4e5; color:var(--wa-warning);"><i class="ti ti-clock"></i></div>
                <div class="wa-label">{{ __('Avg. Session') }}</div>
            </div>
            <p class="wa-value" id="waAvgSession">—</p>
            <div class="wa-delta" id="waAvgSessionDelta" style="color:#5b6b82;">&nbsp;</div>
        </div>
        <div class="wa-card">
            <div class="wa-card-top">
                <div class="wa-icon" style="background:#fdecea; color:var(--wa-danger);"><i class="ti ti-door-exit"></i></div>
                <div class="wa-label">{{ __('Bounce Rate') }}</div>
            </div>
            <p class="wa-value" id="waBounceRate">—</p>
            <div class="wa-delta" id="waBounceDelta" style="color:#5b6b82;">&nbsp;</div>
        </div>
    </div>

    {{-- 2. Charts --}}
    <div class="wa-grid-2" id="revenue" style="scroll-margin-top:90px;">
        <div class="wa-panel">
            <h3>{{ __('Revenue Over Time') }} <span class="wa-badge">{{ __('Last 30 days') }}</span></h3>
            <div class="wa-chart-box"><canvas id="waRevenueChart"></canvas></div>
        </div>
        <div class="wa-panel">
            <h3>{{ __('Top Selling Courses') }} <span class="wa-badge">{{ __('By revenue') }}</span></h3>
            <div class="wa-chart-box"><canvas id="waProductsChart"></canvas></div>
        </div>
    </div>

    {{-- Traffic Sources + Devices --}}
    <div class="wa-grid-2" id="sources" style="scroll-margin-top:90px;">
        <div class="wa-panel">
            <h3>{{ __('Traffic Sources') }} <span class="wa-badge">{{ __('Last 7 days') }}</span></h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center;">
                <div class="wa-chart-box" style="height:220px;"><canvas id="waSourcesChart"></canvas></div>
                <div id="waSourcesLegend"></div>
            </div>
        </div>
        <div class="wa-panel">
            <h3>{{ __('Devices & Browsers') }} <span class="wa-badge">{{ __('Current period') }}</span></h3>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center;">
                <div class="wa-chart-box" style="height:220px;"><canvas id="waDevicesChart"></canvas></div>
                <div id="waDevicesLegend"></div>
            </div>
        </div>
    </div>

    {{-- Activity Feed + Top Pages --}}
    <div class="wa-grid-2" id="activity" style="scroll-margin-top:90px;">
        <div class="wa-panel">
            <h3>
                <span><span class="wa-live-dot"></span>{{ __('Live Activity Feed') }}</span>
                <span class="wa-badge" id="waActivityBadge">—</span>
            </h3>
            <div class="wa-activity-feed" id="waActivityFeed"></div>
        </div>
        <div class="wa-panel" id="pages">
            <h3>{{ __('Top Pages') }} <span class="wa-badge">{{ __('By views') }}</span></h3>
            <div style="overflow-x:auto;">
                <table class="wa-table wa-page-table">
                    <thead>
                        <tr>
                            <th>{{ __('Page') }}</th>
                            <th>{{ __('Views') }}</th>
                            <th>{{ __('Avg Time') }}</th>
                            <th>{{ __('Bounce') }}</th>
                        </tr>
                    </thead>
                    <tbody id="waPagesTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 4. Funnel & 5. Abandoned --}}
    <div class="wa-grid-2" id="funnel" style="scroll-margin-top:90px;">
        <div class="wa-panel">
            <h3>{{ __('Conversion Funnel') }} <span class="wa-badge" id="waFunnelBadge">—</span></h3>
            <div class="wa-funnel" id="waFunnel"></div>
        </div>
        <div class="wa-panel" id="abandoned">
            <h3>{{ __('Abandoned Carts') }} <span class="wa-badge" style="background:#fdecea; color:var(--wa-danger);">{{ __('Needs attention') }}</span></h3>
            <div style="padding:10px 0;">
                <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f0f3f7;">
                    <span style="color:#5b6b82;">{{ __('Abandoned carts') }}</span>
                    <span style="font-weight:700; color:var(--wa-danger);" id="waAbandonedCount">—</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f0f3f7;">
                    <span style="color:#5b6b82;">{{ __('Estimated lost revenue') }}</span>
                    <span style="font-weight:700; color:var(--wa-danger);" id="waLostRevenue">—</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:14px 0;">
                    <span style="color:#5b6b82;">{{ __('Recovery potential') }}</span>
                    <span style="font-weight:700; color:var(--wa-success);" id="waRecoveryPotential">—</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Orders Table — ALL orders with search + pagination --}}
    <div class="wa-panel" style="margin-bottom:24px; scroll-margin-top:90px;" id="orders">
        <h3 style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <span>{{ __('All Orders') }} <span class="wa-badge" id="waOrdersCount">—</span></span>
            <span style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input id="waOrdersSearch" type="text" placeholder="Search by ID, customer, status, total..."
                       style="padding:6px 12px; border:1px solid #e4eaf2; border-radius:6px; font-size:12px; width:280px;" />
                <button type="button" id="waOrdersExport"
                        style="padding:6px 14px; border:1px solid #2e7d32; background:#2e7d32; color:#fff; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="ti ti-download"></i> {{ __('Export CSV') }}
                </button>
            </span>
        </h3>
        <div style="overflow-x:auto;">
            <table class="wa-table">
                <thead>
                    <tr>
                        <th>{{ __('Order ID') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Total') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody id="waOrdersTableBody">
                    <tr><td colspan="5" class="wa-loading">{{ __('Loading...') }}</td></tr>
                </tbody>
            </table>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:1px solid #f0f3f7; margin-top:12px;">
            <span id="waOrdersPaginationInfo" style="font-size:12px; color:#5b6b82;"></span>
            <div style="display:flex; gap:8px;">
                <button id="waOrdersPrev" style="padding:6px 12px; border:1px solid #e4eaf2; background:#fff; border-radius:6px; font-size:12px; cursor:pointer;">« Prev</button>
                <button id="waOrdersNext" style="padding:6px 12px; border:1px solid #e4eaf2; background:#fff; border-radius:6px; font-size:12px; cursor:pointer;">Next »</button>
            </div>
        </div>
    </div>

    {{-- Geographic Breakdown --}}
    <div class="wa-panel" id="geography" style="margin-bottom:24px; scroll-margin-top:90px;">
        <h3>{{ __('Visitors by Country') }} <span class="wa-badge" id="waGeoBadge">—</span></h3>
        <div id="waGeoList"></div>
    </div>

    {{-- 6. Top Products List & 7. Users --}}
    <div class="wa-grid-2">
        <div class="wa-panel" id="courses" style="scroll-margin-top:90px;">
            <h3>{{ __('Top Courses') }}</h3>
            <div id="waTopProductsList"></div>
        </div>
        <div class="wa-panel" id="customers" style="scroll-margin-top:90px;">
            <h3>{{ __('Customer Insights') }}</h3>
            <div style="padding:10px 0;">
                <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f0f3f7;">
                    <span style="color:#5b6b82;">{{ __('Total Customers') }}</span>
                    <span style="font-weight:700; color:var(--wa-primary);" id="waTotalCustomers">—</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:14px 0; border-bottom:1px solid #f0f3f7;">
                    <span style="color:#5b6b82;">{{ __('New this month') }}</span>
                    <span style="font-weight:700; color:var(--wa-success);" id="waNewCustomers">—</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding:14px 0;">
                    <span style="color:#5b6b82;">{{ __('Returning') }}</span>
                    <span style="font-weight:700; color:var(--wa-warning);" id="waReturningCustomers">—</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 7.5 LearnPress Orders — bridged from WordPress via /wp-json/erp/v1 --}}
    <div id="learnpress" style="scroll-margin-top:90px; margin-top:28px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <h2 class="wa-title" style="font-size:18px;">
                <i class="ti ti-school" style="color:#6a1b9a; margin-right:8px;"></i>
                {{ __('LearnPress Orders') }}
            </h2>
            <span id="waLpBadge" class="wa-badge" style="padding:6px 12px; font-size:12px;">{{ __('Loading…') }}</span>
        </div>

        {{-- Setup-required banner (shown only when bridge env vars are missing) --}}
        <div id="waLpSetupPanel" class="wa-panel" style="display:none; border-color:#ffd699; background:#fffaf2; margin-bottom:20px;">
            <h3 style="color:#8a6100;"><i class="ti ti-settings" style="margin-right:6px;"></i>{{ __('Bridge not configured') }}</h3>
            <p style="font-size:13px; color:#5b6b82; margin:6px 0;">
                {{ __('Upload the MU plugin from /wordpress-plugin/erp-bridge.php to your WordPress site and set the shared token in both wp-config.php and the ERP .env file.') }}
            </p>
            <p id="waLpSetupDetail" style="font-size:12px; color:#8a6100; margin:0;"></p>
        </div>

        {{-- KPI cards: today / week / month / all --}}
        <div class="wa-cards" id="waLpKpis" style="margin-bottom:22px;">
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#e3f2fd; color:#1565c0;"><i class="ti ti-calendar-due"></i></div>
                    <div class="wa-label">{{ __('Today') }}</div>
                </div>
                <p class="wa-value" id="waLpTodayRev">—</p>
                <div class="wa-delta" id="waLpTodayCount" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#e8f5e9; color:#2e7d32;"><i class="ti ti-calendar-week"></i></div>
                    <div class="wa-label">{{ __('This Week') }}</div>
                </div>
                <p class="wa-value" id="waLpWeekRev">—</p>
                <div class="wa-delta" id="waLpWeekCount" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#fff4e5; color:#e65100;"><i class="ti ti-calendar-month"></i></div>
                    <div class="wa-label">{{ __('This Month') }}</div>
                </div>
                <p class="wa-value" id="waLpMonthRev">—</p>
                <div class="wa-delta" id="waLpMonthCount" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#f3e5f5; color:#6a1b9a;"><i class="ti ti-cash"></i></div>
                    <div class="wa-label">{{ __('All-time Revenue') }}</div>
                </div>
                <p class="wa-value" id="waLpAllRev">—</p>
                <div class="wa-delta" id="waLpAllCount" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#fdecea; color:#c62828;"><i class="ti ti-progress-alert"></i></div>
                    <div class="wa-label">{{ __('Pending / Failed') }}</div>
                </div>
                <p class="wa-value" id="waLpPending">—</p>
                <div class="wa-delta" id="waLpStatusBreakdown" style="color:#5b6b82;">&nbsp;</div>
            </div>
        </div>

        {{-- Charts row: monthly trend + status donut --}}
        <div class="wa-grid-2" style="margin-bottom:22px;">
            <div class="wa-panel">
                <h3 style="margin-bottom:14px;">
                    <i class="ti ti-trending-up" style="color:#6a1b9a;"></i> {{ __('LP Monthly Trend (6 months)') }}
                </h3>
                <div style="position:relative; height:230px;">
                    <canvas id="waLpTrendChart"></canvas>
                </div>
            </div>
            <div class="wa-panel">
                <h3 style="margin-bottom:14px;">
                    <i class="ti ti-chart-donut" style="color:#6a1b9a;"></i> {{ __('Status Breakdown') }}
                </h3>
                <div style="position:relative; height:230px;">
                    <canvas id="waLpStatusChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Top courses + Recent orders --}}
        <div class="wa-grid-2" style="margin-bottom:8px;">
            <div class="wa-panel">
                <h3>
                    <i class="ti ti-award" style="color:#e65100;"></i> {{ __('Top Selling Courses') }}
                    <span class="wa-badge" style="background:#fff4e5; color:#e65100; margin-left:6px;">{{ __('Top 10') }}</span>
                </h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Course') }}</th>
                                <th style="text-align:center;">{{ __('Sales') }}</th>
                                <th>{{ __('Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waLpCoursesBody">
                            <tr><td colspan="4" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="wa-panel">
                <h3>
                    <i class="ti ti-list-details" style="color:#1565c0;"></i> {{ __('Recent LP Orders') }}
                    <span class="wa-badge" style="margin-left:6px;">{{ __('Latest 20') }}</span>
                </h3>
                <div style="overflow-x:auto; max-height:380px; overflow-y:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th>{{ __('Total') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waLpOrdersBody">
                            <tr><td colspan="6" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- 7.6 Course Activations — completed orders broken out per-course --}}
    <div id="activations" style="scroll-margin-top:90px; margin-top:28px;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
            <h2 class="wa-title" style="font-size:18px;">
                <i class="ti ti-user-check" style="color:#2e7d32; margin-right:8px;"></i>
                {{ __('Course Activations') }}
            </h2>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <label for="waActPeriod" style="font-size:13px; color:#5b6b82; font-weight:600;">
                    <i class="ti ti-calendar-filter"></i> {{ __('Period') }}:
                </label>
                <select id="waActPeriod" class="form-select"
                        style="padding:6px 10px; font-size:13px; border:1px solid #e4eaf2; border-radius:6px; min-width:160px;">
                    <option value="today">{{ __('Today') }}</option>
                    <option value="yesterday">{{ __('Yesterday') }}</option>
                    <option value="this_week">{{ __('This Week') }}</option>
                    <option value="last_7d">{{ __('Last 7 Days') }}</option>
                    <option value="this_month" selected>{{ __('This Month') }}</option>
                    <option value="last_month">{{ __('Last Month') }}</option>
                    <option value="last_30d">{{ __('Last 30 Days') }}</option>
                    <option value="this_year">{{ __('This Year') }}</option>
                </select>
                <input type="search" id="waActSearch" placeholder="{{ __('Search student or course...') }}"
                       style="padding:6px 10px; font-size:13px; border:1px solid #e4eaf2; border-radius:6px; min-width:220px;">
                <button type="button" id="waActExport"
                        style="padding:6px 14px; border:1px solid #2e7d32; background:#2e7d32; color:#fff; border-radius:6px; font-size:13px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="ti ti-download"></i> {{ __('Export CSV') }}
                </button>
                <span id="waActBadge" class="wa-badge" style="padding:6px 12px; font-size:12px;">{{ __('Loading…') }}</span>
            </div>
        </div>

        {{-- Summary stats --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin-bottom:18px;">
            <div class="wa-card" style="text-align:center;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#e8f5e9; color:#2e7d32;"><i class="ti ti-user-check"></i></div>
                </div>
                <div class="wa-label">{{ __('Total Activations') }}</div>
                <div id="waActTotal" class="wa-value" style="font-size:24px; color:#2e7d32;">—</div>
                <div id="waActPeriodLabel" style="font-size:11px; color:#5b6b82; margin-top:2px;"></div>
            </div>
            <div class="wa-card" style="text-align:center;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#e3f2fd; color:#1565c0;"><i class="ti ti-users"></i></div>
                </div>
                <div class="wa-label">{{ __('Unique Students') }}</div>
                <div id="waActStudents" class="wa-value" style="font-size:24px; color:#1565c0;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Distinct users') }}</div>
            </div>
            <div class="wa-card" style="text-align:center;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#fff4e5; color:#e65100;"><i class="ti ti-currency-dollar"></i></div>
                </div>
                <div class="wa-label">{{ __('Total Revenue') }}</div>
                <div id="waActRevenue" class="wa-value" style="font-size:20px; color:#e65100;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Sum of activation values') }}</div>
            </div>
            <div class="wa-card" style="text-align:center;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#f3e5f5; color:#6a1b9a;"><i class="ti ti-calculator"></i></div>
                </div>
                <div class="wa-label">{{ __('Avg per Activation') }}</div>
                <div id="waActAvg" class="wa-value" style="font-size:20px; color:#6a1b9a;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Mean course value') }}</div>
            </div>
        </div>

        {{-- Activations table --}}
        <div class="wa-panel">
            <h3 style="margin-bottom:10px;">
                <i class="ti ti-list-details" style="color:#2e7d32;"></i> {{ __('Activations List') }}
                <span class="wa-badge" id="waActListBadge" style="margin-left:6px;">—</span>
            </h3>
            <div style="overflow-x:auto; max-height:560px; overflow-y:auto;">
                <table class="wa-table">
                    <thead style="position:sticky; top:0; background:#fff; z-index:1;">
                        <tr>
                            <th>#</th>
                            <th>{{ __('Student') }}</th>
                            <th>{{ __('Course') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Payment') }}</th>
                            <th>{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody id="waActBody">
                        <tr><td colspan="6" class="wa-loading">{{ __('Loading...') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 8. CRM Dashboard — comprehensive sales & pipeline overview --}}
    <div id="crm" style="scroll-margin-top:90px; margin-top:28px;">

        {{-- Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <h2 class="wa-title" style="font-size:18px;">
                <i class="ti ti-database" style="color:var(--wa-primary); margin-right:8px;"></i>
                {{ __('CRM Dashboard') }}
            </h2>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <label for="waCrmPipeline" style="font-size:13px; color:#5b6b82; font-weight:600;">
                    <i class="ti ti-filter"></i> {{ __('Pipeline') }}:
                </label>
                <select id="waCrmPipeline" class="form-select"
                        style="padding:6px 10px; font-size:13px; border:1px solid #e4eaf2; border-radius:6px; min-width:180px;">
                    <option value="all">{{ __('All pipelines') }}</option>
                </select>
                <span id="waCrmBadge" class="wa-badge" style="padding:6px 12px; font-size:12px;">{{ __('Loading…') }}</span>
            </div>
        </div>

        {{-- TODAY'S SNAPSHOT banner --}}
        <div style="background:linear-gradient(135deg,#1565c0 0%,#1976d2 60%,#1e88e5 100%); border-radius:14px; padding:20px 22px; margin-bottom:22px; color:#fff; box-shadow:0 4px 20px rgba(21,101,192,.25);">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <i class="ti ti-sun" style="font-size:20px;"></i>
                <span style="font-size:15px; font-weight:700; letter-spacing:.3px;">{{ __("Today's Activity") }}</span>
                <span style="font-size:12px; opacity:.7; margin-left:6px;">{{ now()->format('l, d M Y') }}</span>
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(155px,1fr)); gap:14px;">
                <div style="background:rgba(255,255,255,.18); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:.6px; opacity:.8; margin-bottom:6px;"><i class="ti ti-user-plus"></i> {{ __('Leads Today') }}</div>
                    <div id="waTodayLeads" style="font-size:30px; font-weight:800; line-height:1;">—</div>
                    <div id="waTodayLeadsWeek" style="font-size:11px; opacity:.7; margin-top:5px;"></div>
                </div>
                <div style="background:rgba(255,255,255,.18); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:.6px; opacity:.8; margin-bottom:6px;"><i class="ti ti-trophy"></i> {{ __('Deals Won Today') }}</div>
                    <div id="waTodayDealsWon" style="font-size:30px; font-weight:800; line-height:1;">—</div>
                    <div id="waTodayDealsWeek" style="font-size:11px; opacity:.7; margin-top:5px;"></div>
                </div>
                <div style="background:rgba(255,255,255,.18); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:.6px; opacity:.8; margin-bottom:6px;"><i class="ti ti-calendar-event"></i> {{ __('Meetings Today') }}</div>
                    <div id="waTodayMeetings" style="font-size:30px; font-weight:800; line-height:1;">—</div>
                    <div id="waTodayMeetingsSubtitle" style="font-size:11px; opacity:.7; margin-top:5px;"></div>
                </div>
                <div style="background:rgba(220,50,50,.28); border-radius:10px; padding:14px 16px;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:.6px; opacity:.85; margin-bottom:6px;"><i class="ti ti-alert-circle"></i> {{ __('Installments Due Today') }}</div>
                    <div id="waTodayInstDue" style="font-size:30px; font-weight:800; line-height:1;">—</div>
                    <div id="waTodayInstDueAmt" style="font-size:12px; opacity:.85; margin-top:5px; font-weight:600;"></div>
                </div>
            </div>
        </div>

        {{-- PERFORMANCE METRICS (rates + week revenue) --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(145px,1fr)); gap:12px; margin-bottom:22px;">
            <div class="wa-card" style="text-align:center; padding:16px 12px;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#e3f2fd; color:#1565c0;"><i class="ti ti-route"></i></div>
                </div>
                <div class="wa-label">{{ __('Conversion Rate') }}</div>
                <div id="waRateConversion" class="wa-value" style="font-size:26px; color:#1565c0;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Leads → Deals') }}</div>
            </div>
            <div class="wa-card" style="text-align:center; padding:16px 12px;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#e8f5e9; color:#2e7d32;"><i class="ti ti-trophy"></i></div>
                </div>
                <div class="wa-label">{{ __('Win Rate') }}</div>
                <div id="waRateWin" class="wa-value" style="font-size:26px; color:#2e7d32;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Won / Total Deals') }}</div>
            </div>
            <div class="wa-card" style="text-align:center; padding:16px 12px;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#fff4e5; color:#e65100;"><i class="ti ti-currency-dollar"></i></div>
                </div>
                <div class="wa-label">{{ __('Avg Deal Size') }}</div>
                <div id="waRateAvgDeal" class="wa-value" style="font-size:22px; color:#e65100;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Revenue / Purchases') }}</div>
            </div>
            <div class="wa-card" style="text-align:center; padding:16px 12px;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#fdecea; color:#c62828;"><i class="ti ti-receipt-off"></i></div>
                </div>
                <div class="wa-label">{{ __('Total Unpaid') }}</div>
                <div id="waRateUnpaid" class="wa-value" style="font-size:18px; color:#c62828;">—</div>
                <div id="waRateUnpaidDetail" style="font-size:11px; color:#5b6b82; margin-top:2px;"></div>
            </div>
            <div class="wa-card" style="text-align:center; padding:16px 12px;">
                <div style="display:flex; justify-content:center; margin-bottom:8px;">
                    <div class="wa-icon" style="background:#f3e5f5; color:#6a1b9a;"><i class="ti ti-cash"></i></div>
                </div>
                <div class="wa-label">{{ __('This Week Revenue') }}</div>
                <div id="waRateWeekRev" class="wa-value" style="font-size:18px; color:#6a1b9a;">—</div>
                <div style="font-size:11px; color:#5b6b82; margin-top:2px;">{{ __('Deal Purchases') }}</div>
            </div>
        </div>

        {{-- OVERALL KPI CARDS --}}
        <div style="font-size:13px; font-weight:700; color:#1a2332; margin-bottom:10px; display:flex; align-items:center; gap:6px;">
            <i class="ti ti-chart-bar" style="color:var(--wa-primary);"></i> {{ __('Overall KPIs') }}
        </div>
        <div class="wa-cards" style="margin-bottom:22px;">
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon"><i class="ti ti-user-plus"></i></div>
                    <div class="wa-label">{{ __('Leads') }}</div>
                </div>
                <p class="wa-value" id="waCrmLeadsTotal">—</p>
                <div class="wa-delta wa-delta-up" id="waCrmLeadsNew">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#e8f5e9; color:var(--wa-success);"><i class="ti ti-briefcase"></i></div>
                    <div class="wa-label">{{ __('Deals') }}</div>
                </div>
                <p class="wa-value" id="waCrmDealsTotal">—</p>
                <div class="wa-delta" id="waCrmDealsBreakdown" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#fff4e5; color:var(--wa-warning);"><i class="ti ti-shopping-bag"></i></div>
                    <div class="wa-label">{{ __('Deal Purchases') }}</div>
                </div>
                <p class="wa-value" id="waCrmPurchasesCount">—</p>
                <div class="wa-delta" id="waCrmPurchasesRevenue" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#fdecea; color:var(--wa-danger);"><i class="ti ti-credit-card"></i></div>
                    <div class="wa-label">{{ __('Installments') }}</div>
                </div>
                <p class="wa-value" id="waCrmInstallmentsUnpaid">—</p>
                <div class="wa-delta" id="waCrmInstallmentsOverdue" style="color:#5b6b82;">&nbsp;</div>
            </div>
            <div class="wa-card">
                <div class="wa-card-top">
                    <div class="wa-icon" style="background:#f3e5f5; color:#7b1fa2;"><i class="ti ti-calendar-event"></i></div>
                    <div class="wa-label">{{ __('Sales Meetings') }}</div>
                </div>
                <p class="wa-value" id="waCrmMeetingsUpcoming">—</p>
                <div class="wa-delta" id="waCrmMeetingsLast30" style="color:#5b6b82;">&nbsp;</div>
            </div>
        </div>

        {{-- CHARTS ROW: Monthly Trend + Leads by Stage --}}
        <div class="wa-grid-2" style="margin-bottom:22px;">
            <div class="wa-panel">
                <h3 style="margin-bottom:14px;">
                    <i class="ti ti-trending-up" style="color:var(--wa-primary);"></i> {{ __('Monthly Trend (6 months)') }}
                </h3>
                <div style="position:relative; height:230px;">
                    <canvas id="waCrmTrendChart"></canvas>
                </div>
            </div>
            <div class="wa-panel">
                <h3 style="margin-bottom:14px;">
                    <i class="ti ti-funnel" style="color:#7b1fa2;"></i> {{ __('Leads by Stage') }}
                </h3>
                <div style="position:relative; height:230px;">
                    <canvas id="waCrmStageChart"></canvas>
                </div>
            </div>
        </div>

        {{-- TOP PERFORMERS + RECENT DEAL PURCHASES --}}
        <div class="wa-grid-2" style="margin-bottom:22px;">
            <div class="wa-panel">
                <h3>
                    <i class="ti ti-award" style="color:#e65100;"></i> {{ __('Top Performers') }}
                    <span class="wa-badge" style="background:#fff4e5; color:#e65100; margin-left:6px;">{{ __('By Deals Won') }}</span>
                </h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Salesperson') }}</th>
                                <th style="text-align:center;">{{ __('Won') }}</th>
                                <th style="text-align:center;">{{ __('Lost') }}</th>
                                <th style="text-align:center;">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waTopPerformersBody">
                            <tr><td colspan="5" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="wa-panel">
                <h3>
                    <i class="ti ti-shopping-cart" style="color:#1565c0;"></i> {{ __('Recent Deal Purchases') }}
                    <span class="wa-badge" style="margin-left:6px;">{{ __('Latest 10') }}</span>
                </h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>{{ __('Deal') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Paid') }}</th>
                                <th>{{ __('Due') }}</th>
                                <th>{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waRecentPurchasesBody">
                            <tr><td colspan="5" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- TODAY'S MEETINGS (hidden when empty) --}}
        <div class="wa-panel" id="waTodayMeetingsPanel" style="display:none; margin-bottom:22px; border-color:#bbdefb; background:#f0f7ff;">
            <h3 style="color:#1565c0;">
                <i class="ti ti-calendar-event" style="margin-right:6px;"></i>{{ __("Today's Meetings") }}
                <span class="wa-badge" style="background:#bbdefb; color:#1565c0; margin-left:6px;" id="waTodayMeetingsBadge">—</span>
            </h3>
            <div style="overflow-x:auto;">
                <table class="wa-table">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Lead') }}</th>
                            <th>{{ __('Deal') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Duration') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="waTodayMeetingsBody"></tbody>
                </table>
            </div>
        </div>

        {{-- RECENT LEADS + RECENT DEALS --}}
        <div class="wa-grid-2" style="margin-bottom:22px;">
            <div class="wa-panel">
                <h3>{{ __('Recent Leads') }} <span class="wa-badge">{{ __('Latest 10') }}</span></h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Phone') }}</th>
                                <th>{{ __('Country') }}</th>
                                <th>{{ __('Stage') }}</th>
                                <th>{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waCrmLeadsBody">
                            <tr><td colspan="5" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="wa-panel">
                <h3>{{ __('Recent Deals') }} <span class="wa-badge">{{ __('Latest 10') }}</span></h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>{{ __('Deal') }}</th>
                                <th>{{ __('Lead') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waCrmDealsBody">
                            <tr><td colspan="4" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- UPCOMING MEETINGS + OVERDUE INSTALLMENTS --}}
        <div class="wa-grid-2" style="margin-bottom:22px;">
            <div class="wa-panel">
                <h3>{{ __('Upcoming Sales Meetings') }} <span class="wa-badge" id="waCrmMeetingsBadge">—</span></h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Lead') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waCrmMeetingsBody">
                            <tr><td colspan="4" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="wa-panel">
                <h3>{{ __('Overdue Installments') }} <span class="wa-badge" style="background:#fdecea; color:var(--wa-danger);" id="waCrmOverdueBadge">—</span></h3>
                <div style="overflow-x:auto;">
                    <table class="wa-table">
                        <thead>
                            <tr>
                                <th>{{ __('Deal / Lead') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Due Date') }}</th>
                            </tr>
                        </thead>
                        <tbody id="waCrmOverdueBody">
                            <tr><td colspan="3" class="wa-loading">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- EXCLUDED MEETINGS (pipeline filter diagnostic) --}}
        <div class="wa-panel" id="waCrmExcludedPanel" style="display:none; margin-top:16px; border-color:#ffd699; background:#fffaf2;">
            <h3 style="color:#8a6100;">
                <span><i class="ti ti-alert-triangle" style="margin-right:6px;"></i>{{ __('Meetings excluded by pipeline filter') }}</span>
                <span class="wa-badge" style="background:#ffd699; color:#8a6100;" id="waCrmExcludedBadge">—</span>
            </h3>
            <p style="font-size:12px; color:#5b6b82; margin:-4px 0 10px;">
                {{ __('These meetings exist for this tenant but are filtered out of the current pipeline. Review and delete or re-link as needed.') }}
            </p>
            <div style="overflow-x:auto;">
                <table class="wa-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Lead') }}</th>
                            <th>{{ __('Deal') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="waCrmExcludedBody"></tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    'use strict';

    // Early status update — runs as soon as script parses
    const _earlyDebug = document.getElementById('waDebug');
    if (_earlyDebug) _earlyDebug.textContent = '[' + new Date().toLocaleTimeString() + '] JS loaded, Chart=' + (typeof Chart !== 'undefined' ? 'OK' : 'MISSING');

    const WA_CONFIG = @json($config);
    const currencyFmt = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: WA_CONFIG.currency,
        maximumFractionDigits: 0
    });
    const numFmt = new Intl.NumberFormat('en-US');

    // Escape HTML to prevent XSS from API data (customer names, product titles, etc.)
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    let revenueChart = null;
    let productsChart = null;

    // Fetch data from our own Laravel endpoint (server-side proxy to WooCommerce)
    async function waFetchAll() {
        const siteKey = WA_CONFIG.site_key || 'arabic';
        const cacheBust = '?_=' + Date.now();
        const url = '/websites/data/' + encodeURIComponent(siteKey) + cacheBust;
        updateDebug('Fetching: ' + url);
        const res = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
            credentials: 'same-origin'
        });
        updateDebug('HTTP ' + res.status + ' ' + res.statusText + ' · ' + (res.headers.get('content-type') || 'no-type'));
        if (!res.ok) {
            const text = await res.text();
            throw new Error('HTTP ' + res.status + ': ' + text.substring(0, 200));
        }
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('JSON parse failed: ' + text.substring(0, 200));
        }
        updateDebug('✅ Parsed: ' + (data.orders||[]).length + ' orders, ' +
            (data.products||[]).length + ' products, ' +
            (data.customers||[]).length + ' customers, fallback=' + data.fallback);
        console.log('[Websites Analytics] Payload:', data);
        return data;
    }

    function updateDebug(msg) {
        const el = document.getElementById('waDebug');
        if (el) {
            const ts = new Date().toLocaleTimeString();
            el.textContent = '[' + ts + '] ' + msg;
        }
    }

    function showBanner(msg) {
        const banner = document.getElementById('waErrorBanner');
        document.getElementById('waErrorText').textContent = msg;
        banner.classList.add('show');
    }

    // ---------- Mock data fallback ----------
    function mockOrders(count = 50) {
        const statuses = ['completed', 'completed', 'completed', 'processing', 'pending', 'cancelled'];
        const firstNames = ['James','Emma','Oliver','Sophia','Liam','Isabella','Noah','Mia','Ethan','Ava','Ahmed','Sara','Mohammed','Fatima'];
        const lastNames = ['Smith','Johnson','Brown','Taylor','Wilson','Khan','Ali','Hassan'];
        const out = [];
        for (let i = 0; i < count; i++) {
            const d = new Date();
            d.setDate(d.getDate() - Math.floor(Math.random() * 30));
            out.push({
                id: 10000 + i,
                status: statuses[Math.floor(Math.random() * statuses.length)],
                total: (Math.random() * 800 + 50).toFixed(2),
                date_created: d.toISOString(),
                billing: {
                    first_name: firstNames[Math.floor(Math.random() * firstNames.length)],
                    last_name: lastNames[Math.floor(Math.random() * lastNames.length)]
                }
            });
        }
        return out;
    }

    function mockProducts() {
        // Course catalog (replaces WooCommerce products — this is an LMS/course-seller)
        const names = [
            'Fiber Optics Fundamentals',
            'Advanced Network Security',
            '5G Technology Essentials',
            'Cloud Infrastructure with AWS',
            'Cisco CCNA Certification Prep',
            'Telecom Engineering Mastery',
            'Cybersecurity for Networks',
            'Wireless Communication Systems',
            'Python for Network Automation',
            'VoIP & SIP Protocol Deep-Dive'
        ];
        return names.map((name, i) => ({
            id: i + 1,
            name,
            price: (Math.random() * 500 + 99).toFixed(2),
            total_sales: Math.floor(Math.random() * 200) + 20
        }));
    }

    function mockCustomers() {
        const total = 1280 + Math.floor(Math.random() * 200);
        const newThis = Math.floor(total * 0.15);
        return { total, new: newThis, returning: total - newThis };
    }

    function mockTracking() {
        return {
            liveVisitors: 18 + Math.floor(Math.random() * 35),
            liveCountries: ['Egypt','UK','Saudi Arabia','UAE','Qatar','Kuwait','Germany','USA'],
            todaySessions: 820 + Math.floor(Math.random() * 400),
            avgSessionSec: 180 + Math.floor(Math.random() * 240),
            bounceRate: (35 + Math.random() * 25).toFixed(1)
        };
    }

    function mockSources() {
        return [
            { label: 'Organic Search', value: 42 + Math.floor(Math.random() * 8), color: '#1976d2' },
            { label: 'Direct',         value: 22 + Math.floor(Math.random() * 5), color: '#42a5f5' },
            { label: 'Social Media',   value: 15 + Math.floor(Math.random() * 5), color: '#7b1fa2' },
            { label: 'Referral',       value: 10 + Math.floor(Math.random() * 4), color: '#ed6c02' },
            { label: 'Email',          value: 6  + Math.floor(Math.random() * 3), color: '#2e7d32' },
            { label: 'Paid Ads',       value: 5  + Math.floor(Math.random() * 3), color: '#d32f2f' }
        ];
    }

    function mockDevices() {
        return [
            { label: 'Mobile',  value: 58 + Math.floor(Math.random() * 5), color: '#1976d2' },
            { label: 'Desktop', value: 32 + Math.floor(Math.random() * 5), color: '#42a5f5' },
            { label: 'Tablet',  value: 10 + Math.floor(Math.random() * 3), color: '#7b1fa2' }
        ];
    }

    function mockGeography() {
        const countries = [
            { flag: '🇪🇬', name: 'Egypt',         visitors: 3200, revenue: 18400 },
            { flag: '🇬🇧', name: 'United Kingdom', visitors: 2100, revenue: 22100 },
            { flag: '🇸🇦', name: 'Saudi Arabia',   visitors: 1850, revenue: 19800 },
            { flag: '🇦🇪', name: 'UAE',           visitors: 1420, revenue: 15200 },
            { flag: '🇶🇦', name: 'Qatar',          visitors:  940, revenue:  9800 },
            { flag: '🇰🇼', name: 'Kuwait',         visitors:  820, revenue:  7400 },
            { flag: '🇺🇸', name: 'United States',  visitors:  780, revenue:  8900 },
            { flag: '🇩🇪', name: 'Germany',        visitors:  520, revenue:  5600 },
            { flag: '🇯🇴', name: 'Jordan',         visitors:  440, revenue:  3800 },
            { flag: '🇲🇦', name: 'Morocco',        visitors:  380, revenue:  2900 }
        ];
        // Add slight randomization
        return countries.map(c => ({ ...c, visitors: c.visitors + Math.floor(Math.random() * 100 - 50) }));
    }

    function mockActivityFeed() {
        const firstNames = ['Ahmed','Sara','Mohammed','Fatima','Omar','Layla','Yousef','Noor','Khaled','Mariam','James','Emma','Oliver','Sophia','Liam'];
        const courses = ['Fiber Optics Fundamentals','5G Technology Essentials','CCNA Certification Prep','Network Security','Cloud Infrastructure','VoIP & SIP','Python for Network Automation'];
        const actionTemplates = [
            { icon: 'ti-shopping-cart-plus', bg:'#e3f2fd', color:'#1976d2', msg: (n,c) => `<b>${n}</b> added <b>${c}</b> to cart` },
            { icon: 'ti-check', bg:'#e8f5e9', color:'#2e7d32', msg: (n,c) => `<b>${n}</b> enrolled in <b>${c}</b>` },
            { icon: 'ti-eye', bg:'#f3e5f5', color:'#7b1fa2', msg: (n,c) => `<b>${n}</b> viewed <b>${c}</b>` },
            { icon: 'ti-user-plus', bg:'#fff4e5', color:'#ed6c02', msg: (n) => `<b>${n}</b> created a new account` },
            { icon: 'ti-currency-dollar', bg:'#e8f5e9', color:'#2e7d32', msg: (n,c) => `<b>${n}</b> completed payment for <b>${c}</b>` },
            { icon: 'ti-search', bg:'#e3f2fd', color:'#1976d2', msg: (n) => `<b>${n}</b> searched for "${['5G','fiber','CCNA','security','cloud'][Math.floor(Math.random()*5)]}"` },
            { icon: 'ti-x', bg:'#fdecea', color:'#d32f2f', msg: (n,c) => `<b>${n}</b> abandoned cart with <b>${c}</b>` },
            { icon: 'ti-star-filled', bg:'#fff9c4', color:'#f57f17', msg: (n,c) => `<b>${n}</b> rated <b>${c}</b> 5 stars` }
        ];
        const activities = [];
        for (let i = 0; i < 20; i++) {
            const template = actionTemplates[Math.floor(Math.random() * actionTemplates.length)];
            const name = firstNames[Math.floor(Math.random() * firstNames.length)];
            const course = courses[Math.floor(Math.random() * courses.length)];
            const minutesAgo = Math.floor(Math.random() * 120) + i * 2;
            activities.push({
                icon: template.icon,
                bg: template.bg,
                color: template.color,
                html: template.msg(name, course),
                minutesAgo
            });
        }
        return activities.sort((a, b) => a.minutesAgo - b.minutesAgo);
    }

    function mockTopPages() {
        return [
            { path: '/',                          views: 12400, avg: 145, bounce: 38 },
            { path: '/courses',                    views:  8900, avg: 210, bounce: 42 },
            { path: '/courses/fiber-optics',       views:  4200, avg: 320, bounce: 28 },
            { path: '/courses/5g-essentials',      views:  3800, avg: 295, bounce: 31 },
            { path: '/courses/ccna-prep',          views:  3100, avg: 340, bounce: 26 },
            { path: '/about-us',                   views:  2600, avg:  95, bounce: 55 },
            { path: '/contact-us',                 views:  2100, avg:  78, bounce: 48 },
            { path: '/cart',                       views:  1800, avg: 180, bounce: 22 },
            { path: '/checkout',                   views:  1200, avg: 240, bounce: 18 },
            { path: '/blog',                       views:  1050, avg: 155, bounce: 52 }
        ];
    }

    function formatDuration(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}m ${s.toString().padStart(2,'0')}s`;
    }

    function timeAgo(minutes) {
        if (minutes < 1) return 'just now';
        if (minutes < 60) return minutes + 'm ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        return Math.floor(hours / 24) + 'd ago';
    }

    // ---------- Calculations ----------
    function calculateRevenue(orders) {
        // Count revenue from any order that was actually paid for
        return orders
            .filter(o => ['completed', 'processing', 'on-hold'].includes(o.status))
            .reduce((sum, o) => sum + parseFloat(o.total || 0), 0);
    }

    function groupOrdersByDay(orders) {
        // Determine span: if a date filter is active, use it. Otherwise use last 30 days.
        // Cap the day count at 90 so the chart never becomes unreadably crowded.
        const today = new Date();
        let from, to;
        if (_waDateRange && (_waDateRange.from || _waDateRange.to)) {
            from = _waDateRange.from || (orders.length ? new Date(Math.min(...orders.map(o => new Date(o.date_created).getTime()))) : new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29));
            to   = _waDateRange.to   || today;
        } else {
            from = new Date(today); from.setDate(from.getDate() - 29);
            to   = today;
        }
        const map = {};
        const dayMs = 24 * 60 * 60 * 1000;
        const totalDays = Math.min(90, Math.max(1, Math.round((to.getTime() - from.getTime()) / dayMs) + 1));
        for (let i = 0; i < totalDays; i++) {
            const d = new Date(from); d.setDate(d.getDate() + i);
            map[d.toISOString().slice(0, 10)] = 0;
        }
        orders.forEach(o => {
            const day = new Date(o.date_created).toISOString().slice(0, 10);
            if (map[day] !== undefined) {
                if (o.status === 'completed' || o.status === 'processing') {
                    map[day] += parseFloat(o.total);
                }
            }
        });
        return map;
    }

    // ---------- Renderers ----------
    function renderCards(orders, totals) {
        const revenue = calculateRevenue(orders);
        const completedOrders = orders.filter(o => ['completed', 'processing', 'on-hold'].includes(o.status)).length;
        const aov = completedOrders > 0 ? revenue / completedOrders : 0;
        const conversion = (2.5 + Math.random() * 2).toFixed(2);

        // Use the true totals reported by the WooCommerce store headers when available
        const totalOrders = (totals && totals.orders) ? totals.orders : orders.length;

        document.getElementById('waRevenue').textContent = currencyFmt.format(revenue);
        document.getElementById('waOrders').textContent = numFmt.format(totalOrders);
        document.getElementById('waAOV').textContent = currencyFmt.format(aov);
        document.getElementById('waConversion').textContent = conversion + '%';

        document.getElementById('waRevenueDelta').textContent = '↑ +12.4% vs last period';
        document.getElementById('waOrdersDelta').textContent = '↑ +8.1% vs last period';
        document.getElementById('waAOVDelta').textContent = '↑ +3.7% vs last period';
        document.getElementById('waAOVDelta').className = 'wa-delta wa-delta-up';
        document.getElementById('waConversionDelta').textContent = '↓ -0.3% vs last period';
        document.getElementById('waConversionDelta').className = 'wa-delta wa-delta-down';
    }

    function renderRevenueChart(orders) {
        if (typeof Chart === 'undefined') return; // skip if Chart.js not loaded
        const grouped = groupOrdersByDay(orders);
        const labels = Object.keys(grouped).map(d => {
            const dt = new Date(d);
            return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        const values = Object.values(grouped);

        if (revenueChart) revenueChart.destroy();
        const ctx = document.getElementById('waRevenueChart').getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 280);
        grad.addColorStop(0, 'rgba(25,118,210,.25)');
        grad.addColorStop(1, 'rgba(25,118,210,0)');

        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Revenue',
                    data: values,
                    borderColor: '#1976d2',
                    backgroundColor: grad,
                    fill: true,
                    tension: .35,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { ticks: { callback: v => currencyFmt.format(v) }, grid: { color: '#f0f3f7' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function renderProductsChart(products) {
        if (typeof Chart === 'undefined') return;
        const top = products.slice(0, 8);
        if (productsChart) productsChart.destroy();
        const ctx = document.getElementById('waProductsChart').getContext('2d');
        productsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: top.map(p => p.name.length > 20 ? p.name.slice(0, 18) + '…' : p.name),
                datasets: [{
                    label: 'Revenue',
                    data: top.map(p => parseFloat(p.price) * (p.total_sales || 0)),
                    backgroundColor: '#1976d2',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { callback: v => currencyFmt.format(v) }, grid: { color: '#f0f3f7' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // Build order row safely using DOM APIs (no innerHTML for untrusted data)
    function buildOrderRow(o) {
        const tr = document.createElement('tr');
        const name = (o.billing && (o.billing.first_name || o.billing.last_name))
            ? `${o.billing.first_name || ''} ${o.billing.last_name || ''}`.trim()
            : 'Guest';
        const date = new Date(o.date_created).toLocaleDateString('en-US');

        const tdId = document.createElement('td');
        const bold = document.createElement('b');
        bold.textContent = '#' + o.id;
        tdId.appendChild(bold);

        const tdName = document.createElement('td');
        tdName.textContent = name;

        const tdTotal = document.createElement('td');
        tdTotal.textContent = currencyFmt.format(parseFloat(o.total));

        const tdStatus = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'wa-status wa-status-' + o.status;
        badge.textContent = o.status;
        tdStatus.appendChild(badge);

        const tdDate = document.createElement('td');
        tdDate.textContent = date;

        tr.appendChild(tdId);
        tr.appendChild(tdName);
        tr.appendChild(tdTotal);
        tr.appendChild(tdStatus);
        tr.appendChild(tdDate);
        return tr;
    }

    // Full orders state for client-side search + pagination
    let _waAllOrders = [];
    const _waPageSize = 25;
    let _waCurrentPage = 1;
    let _waSearchTerm = '';

    function renderOrdersTable(orders) {
        _waAllOrders = orders.slice().sort((a, b) => new Date(b.date_created) - new Date(a.date_created));
        _waCurrentPage = 1;
        renderOrdersPage();
    }

    function renderOrdersPage() {
        const body = document.getElementById('waOrdersTableBody');
        body.replaceChildren();

        // Apply search filter
        const term = _waSearchTerm.trim().toLowerCase();
        const filtered = term === '' ? _waAllOrders : _waAllOrders.filter(o => {
            const name = ((o.billing && o.billing.first_name ? o.billing.first_name : '') + ' ' +
                         (o.billing && o.billing.last_name ? o.billing.last_name : '')).toLowerCase();
            return String(o.id).includes(term) ||
                   name.includes(term) ||
                   (o.status || '').toLowerCase().includes(term) ||
                   String(o.total).includes(term);
        });

        document.getElementById('waOrdersCount').textContent =
            filtered.length + (filtered.length === _waAllOrders.length ? ' total' : ' / ' + _waAllOrders.length);

        if (filtered.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.className = 'wa-loading';
            td.textContent = term ? 'No orders match "' + term + '"' : 'No orders';
            tr.appendChild(td);
            body.appendChild(tr);
            document.getElementById('waOrdersPaginationInfo').textContent = '';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(filtered.length / _waPageSize));
        if (_waCurrentPage > totalPages) _waCurrentPage = totalPages;
        const start = (_waCurrentPage - 1) * _waPageSize;
        const end = Math.min(start + _waPageSize, filtered.length);

        filtered.slice(start, end).forEach(o => body.appendChild(buildOrderRow(o)));

        document.getElementById('waOrdersPaginationInfo').textContent =
            'Showing ' + (start + 1) + '–' + end + ' of ' + filtered.length;
        document.getElementById('waOrdersPrev').disabled = _waCurrentPage <= 1;
        document.getElementById('waOrdersNext').disabled = _waCurrentPage >= totalPages;
    }

    function buildFunnelRow(step) {
        const row = document.createElement('div');
        row.className = 'wa-funnel-row';

        const fill = document.createElement('div');
        fill.className = 'wa-funnel-fill';
        fill.style.width = step.pct + '%';
        row.appendChild(fill);

        const content = document.createElement('div');
        content.className = 'wa-funnel-content';

        const left = document.createElement('div');
        const label = document.createElement('div');
        label.className = 'wa-funnel-label';
        label.textContent = step.label;
        const rate = document.createElement('div');
        rate.className = 'wa-funnel-rate';
        rate.textContent = step.pct.toFixed(2) + '% ' + 'of visitors';
        left.appendChild(label);
        left.appendChild(rate);

        const value = document.createElement('div');
        value.className = 'wa-funnel-value';
        value.textContent = numFmt.format(step.value);

        content.appendChild(left);
        content.appendChild(value);
        row.appendChild(content);
        return row;
    }

    function renderFunnel(orders) {
        const completed = orders.filter(o => o.status === 'completed' || o.status === 'processing').length;
        const productViews = Math.round(completed * 42);
        const addedToCart = Math.round(completed * 8);
        const checkoutStarted = Math.round(completed * 2.8);
        const done = completed;

        const steps = [
            { label: 'Product Views',    value: productViews,    pct: 100 },
            { label: 'Added to Cart',    value: addedToCart,     pct: productViews ? (addedToCart/productViews*100) : 0 },
            { label: 'Checkout Started', value: checkoutStarted, pct: productViews ? (checkoutStarted/productViews*100) : 0 },
            { label: 'Completed Orders', value: done,            pct: productViews ? (done/productViews*100) : 0 }
        ];

        document.getElementById('waFunnelBadge').textContent = steps[3].pct.toFixed(2) + '% overall';

        const container = document.getElementById('waFunnel');
        container.replaceChildren();
        steps.forEach(s => container.appendChild(buildFunnelRow(s)));
    }

    function renderAbandoned(orders) {
        const completed = orders.filter(o => o.status === 'completed' || o.status === 'processing').length;
        const abandoned = Math.round(completed * 1.8);
        const avgCart = calculateRevenue(orders) / (completed || 1);
        const lost = abandoned * avgCart * 0.85;
        const recovery = lost * 0.15;

        document.getElementById('waAbandonedCount').textContent = numFmt.format(abandoned);
        document.getElementById('waLostRevenue').textContent = currencyFmt.format(lost);
        document.getElementById('waRecoveryPotential').textContent = currencyFmt.format(recovery);
    }

    function buildProductRow(p) {
        const item = document.createElement('div');
        item.className = 'wa-product-item';

        const nameEl = document.createElement('div');
        nameEl.className = 'wa-product-name';
        nameEl.textContent = p.name;

        const stats = document.createElement('div');
        stats.className = 'wa-product-stats';
        const soldSpan = document.createElement('span');
        soldSpan.textContent = numFmt.format(p.total_sales || 0) + ' enrolled';
        const revEl = document.createElement('b');
        revEl.textContent = currencyFmt.format(parseFloat(p.price) * (p.total_sales || 0));
        stats.appendChild(soldSpan);
        stats.appendChild(revEl);

        item.appendChild(nameEl);
        item.appendChild(stats);
        return item;
    }

    function renderTopProductsList(products) {
        const top = products.slice(0, 6);
        const container = document.getElementById('waTopProductsList');
        container.replaceChildren();
        top.forEach(p => container.appendChild(buildProductRow(p)));
    }

    function renderCustomers(customers) {
        document.getElementById('waTotalCustomers').textContent = numFmt.format(customers.total);
        document.getElementById('waNewCustomers').textContent = numFmt.format(customers.new);
        document.getElementById('waReturningCustomers').textContent = numFmt.format(customers.returning);
    }

    // ---------- Tracking renderers ----------
    function renderTracking(tracking) {
        document.getElementById('waLiveVisitors').textContent = numFmt.format(tracking.liveVisitors);
        document.getElementById('waLiveCountries').textContent = 'from ' + tracking.liveCountries.slice(0, 4).join(', ') + '...';
        document.getElementById('waTodaySessions').textContent = numFmt.format(tracking.todaySessions);
        document.getElementById('waSessionsDelta').textContent = '↑ +14.2% vs yesterday';
        document.getElementById('waAvgSession').textContent = formatDuration(tracking.avgSessionSec);
        document.getElementById('waAvgSessionDelta').textContent = 'Industry avg: 3m 20s';
        document.getElementById('waBounceRate').textContent = tracking.bounceRate + '%';
        document.getElementById('waBounceDelta').textContent = parseFloat(tracking.bounceRate) < 45 ? 'Below industry avg ✓' : 'Above industry avg';
    }

    let sourcesChart = null;
    let devicesChart = null;

    function buildSourceLegendRow(src, total) {
        const row = document.createElement('div');
        row.className = 'wa-source-row';

        const left = document.createElement('div');
        left.className = 'wa-source-label';
        const dot = document.createElement('span');
        dot.className = 'wa-source-dot';
        dot.style.background = src.color;
        const name = document.createElement('span');
        name.textContent = src.label;
        left.appendChild(dot);
        left.appendChild(name);

        const right = document.createElement('div');
        right.className = 'wa-source-value';
        right.textContent = src.value + '%';
        const pct = document.createElement('span');
        pct.className = 'wa-source-pct';
        pct.textContent = '(' + Math.round(src.value * total / 100).toLocaleString() + ' visits)';
        right.appendChild(pct);

        row.appendChild(left);
        row.appendChild(right);
        return row;
    }

    function renderSourcesChart(sources, totalVisitors) {
        if (typeof Chart === 'undefined') return;
        if (sourcesChart) sourcesChart.destroy();
        const ctx = document.getElementById('waSourcesChart').getContext('2d');
        sourcesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: sources.map(s => s.label),
                datasets: [{
                    data: sources.map(s => s.value),
                    backgroundColor: sources.map(s => s.color),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        const legend = document.getElementById('waSourcesLegend');
        legend.replaceChildren();
        sources.forEach(s => legend.appendChild(buildSourceLegendRow(s, totalVisitors)));
    }

    function renderDevicesChart(devices) {
        if (typeof Chart === 'undefined') return;
        if (devicesChart) devicesChart.destroy();
        const ctx = document.getElementById('waDevicesChart').getContext('2d');
        devicesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: devices.map(d => d.label),
                datasets: [{
                    data: devices.map(d => d.value),
                    backgroundColor: devices.map(d => d.color),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        const legend = document.getElementById('waDevicesLegend');
        legend.replaceChildren();
        devices.forEach(d => {
            const row = document.createElement('div');
            row.className = 'wa-source-row';
            const left = document.createElement('div');
            left.className = 'wa-source-label';
            const dot = document.createElement('span');
            dot.className = 'wa-source-dot';
            dot.style.background = d.color;
            const name = document.createElement('span');
            name.textContent = d.label;
            left.appendChild(dot);
            left.appendChild(name);
            const right = document.createElement('div');
            right.className = 'wa-source-value';
            right.textContent = d.value + '%';
            row.appendChild(left);
            row.appendChild(right);
            legend.appendChild(row);
        });
    }

    function buildActivityItem(act) {
        const item = document.createElement('div');
        item.className = 'wa-activity-item';

        const icon = document.createElement('div');
        icon.className = 'wa-activity-icon';
        icon.style.background = act.bg;
        icon.style.color = act.color;
        const iconI = document.createElement('i');
        iconI.className = 'ti ' + act.icon;
        icon.appendChild(iconI);

        const body = document.createElement('div');
        body.className = 'wa-activity-body';
        const text = document.createElement('div');
        text.className = 'wa-activity-text';
        // act.html contains pre-escaped template strings with trusted static HTML (no user input).
        // Names/courses come from our static mockActivityFeed arrays, not from user input.
        text.innerHTML = act.html;  // safe: static data only
        const time = document.createElement('div');
        time.className = 'wa-activity-time';
        time.textContent = timeAgo(act.minutesAgo);

        body.appendChild(text);
        body.appendChild(time);

        item.appendChild(icon);
        item.appendChild(body);
        return item;
    }

    function renderActivityFeed(activities) {
        const container = document.getElementById('waActivityFeed');
        container.replaceChildren();
        activities.forEach(a => container.appendChild(buildActivityItem(a)));
        document.getElementById('waActivityBadge').textContent = activities.length + ' events';
    }

    function buildPageRow(page) {
        const tr = document.createElement('tr');

        const tdPath = document.createElement('td');
        const pathSpan = document.createElement('span');
        pathSpan.style.fontFamily = 'monospace';
        pathSpan.style.fontSize = '12px';
        pathSpan.style.color = 'var(--wa-primary)';
        pathSpan.textContent = page.path;
        tdPath.appendChild(pathSpan);

        const tdViews = document.createElement('td');
        tdViews.innerHTML = '';
        const viewsB = document.createElement('b');
        viewsB.textContent = numFmt.format(page.views);
        tdViews.appendChild(viewsB);

        const tdAvg = document.createElement('td');
        tdAvg.textContent = formatDuration(page.avg);

        const tdBounce = document.createElement('td');
        const bounceSpan = document.createElement('span');
        bounceSpan.style.color = page.bounce > 50 ? 'var(--wa-danger)' : page.bounce > 35 ? 'var(--wa-warning)' : 'var(--wa-success)';
        bounceSpan.style.fontWeight = '600';
        bounceSpan.textContent = page.bounce + '%';
        tdBounce.appendChild(bounceSpan);

        tr.appendChild(tdPath);
        tr.appendChild(tdViews);
        tr.appendChild(tdAvg);
        tr.appendChild(tdBounce);
        return tr;
    }

    function renderTopPages(pages) {
        const body = document.getElementById('waPagesTableBody');
        body.replaceChildren();
        pages.forEach(p => body.appendChild(buildPageRow(p)));
    }

    function buildGeoRow(country, max) {
        const row = document.createElement('div');
        row.className = 'wa-geo-row';

        const flag = document.createElement('span');
        flag.className = 'wa-geo-flag';
        flag.textContent = country.flag;

        const name = document.createElement('span');
        name.style.flex = '1';
        name.style.fontWeight = '500';
        name.textContent = country.name;

        const bar = document.createElement('div');
        bar.className = 'wa-geo-bar';
        const fill = document.createElement('div');
        fill.className = 'wa-geo-fill';
        fill.style.width = (country.visitors / max * 100) + '%';
        bar.appendChild(fill);

        const visitors = document.createElement('span');
        visitors.style.minWidth = '100px';
        visitors.style.textAlign = 'right';
        visitors.style.color = '#5b6b82';
        visitors.textContent = numFmt.format(country.visitors) + ' visitors';

        const revenue = document.createElement('span');
        revenue.style.minWidth = '100px';
        revenue.style.textAlign = 'right';
        revenue.style.fontWeight = '600';
        revenue.style.color = 'var(--wa-primary)';
        revenue.style.marginLeft = '16px';
        revenue.textContent = currencyFmt.format(country.revenue);

        row.appendChild(flag);
        row.appendChild(name);
        row.appendChild(bar);
        row.appendChild(visitors);
        row.appendChild(revenue);
        return row;
    }

    function renderGeography(countries) {
        const max = Math.max(...countries.map(c => c.visitors));
        const total = countries.reduce((s, c) => s + c.visitors, 0);
        document.getElementById('waGeoBadge').textContent = countries.length + ' countries · ' + numFmt.format(total) + ' visitors';
        const container = document.getElementById('waGeoList');
        container.replaceChildren();
        countries.sort((a, b) => b.visitors - a.visitors).forEach(c => container.appendChild(buildGeoRow(c, max)));
    }

    // ---------- Orchestration ----------
    window.waLoadAll = async function() {
        updateDebug('Starting waLoadAll…');
        try {
            await _waLoadAllInner();
        } catch (e) {
            console.error('[Websites Analytics] fatal:', e);
            updateDebug('❌ FATAL: ' + e.message);
        }
    };

    let _waApiTotals = null;

    // Full dataset cache so the date filter can re-render without re-fetching
    let _waDataset = null;          // { orders, products, customers, tracking, sources, devices, activities, topPages, geography }
    let _waDateRange = null;        // { from: Date|null, to: Date|null, label: string }  null = all time

    /**
     * Filter an orders array by the current _waDateRange.
     * Falsy range means "all time" — return the array unchanged.
     */
    function filterOrdersByDate(orders) {
        if (!_waDateRange || (!_waDateRange.from && !_waDateRange.to)) return orders;
        const fromMs = _waDateRange.from ? _waDateRange.from.getTime() : -Infinity;
        const toMs   = _waDateRange.to   ? _waDateRange.to.getTime()   : +Infinity;
        return orders.filter(o => {
            const t = o.date_created ? new Date(o.date_created).getTime() : 0;
            return t >= fromMs && t <= toMs;
        });
    }

    /**
     * Translate a preset key into a {from, to, label} pair.
     * `null` for `from`/`to` means "no bound".
     */
    function buildDateRange(preset, customFrom, customTo) {
        const now = new Date();
        const startOfDay = (d) => { const x = new Date(d); x.setHours(0,0,0,0); return x; };
        const endOfDay   = (d) => { const x = new Date(d); x.setHours(23,59,59,999); return x; };
        switch (preset) {
            case 'today':      return { from: startOfDay(now), to: endOfDay(now), label: 'Today' };
            case 'yesterday': {
                const y = new Date(now); y.setDate(y.getDate() - 1);
                return { from: startOfDay(y), to: endOfDay(y), label: 'Yesterday' };
            }
            case '7d': {
                const f = new Date(now); f.setDate(f.getDate() - 6);
                return { from: startOfDay(f), to: endOfDay(now), label: 'Last 7 days' };
            }
            case '30d': {
                const f = new Date(now); f.setDate(f.getDate() - 29);
                return { from: startOfDay(f), to: endOfDay(now), label: 'Last 30 days' };
            }
            case '90d': {
                const f = new Date(now); f.setDate(f.getDate() - 89);
                return { from: startOfDay(f), to: endOfDay(now), label: 'Last 90 days' };
            }
            case 'this_month': {
                const f = new Date(now.getFullYear(), now.getMonth(), 1);
                return { from: f, to: endOfDay(now), label: 'This month' };
            }
            case 'last_month': {
                const f = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const t = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59, 999);
                return { from: f, to: t, label: 'Last month' };
            }
            case 'this_year': {
                const f = new Date(now.getFullYear(), 0, 1);
                return { from: f, to: endOfDay(now), label: 'This year' };
            }
            case 'all':       return { from: null, to: null, label: 'All time' };
            case 'custom': {
                const f = customFrom ? startOfDay(new Date(customFrom)) : null;
                const t = customTo   ? endOfDay(new Date(customTo))     : null;
                return { from: f, to: t, label: 'Custom' };
            }
            default: return { from: null, to: null, label: 'All time' };
        }
    }

    /**
     * Render every section using the currently-cached dataset and date filter.
     * Called on initial load AND on every date-filter change.
     */
    function renderAll() {
        if (!_waDataset) return;
        const { products, customers, tracking, sources, devices, activities, topPages, geography } = _waDataset;
        const filteredOrders = filterOrdersByDate(_waDataset.orders);

        // Update header summary text
        const summary = document.getElementById('waDateSummary');
        if (summary) {
            const fmt = d => d ? d.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' }) : '—';
            const lbl = (_waDateRange && _waDateRange.label) ? _waDateRange.label : 'All time';
            const range = (_waDateRange && (_waDateRange.from || _waDateRange.to))
                ? `${fmt(_waDateRange.from)} → ${fmt(_waDateRange.to)}` : 'All orders';
            summary.textContent = `${lbl} · ${range} · ${filteredOrders.length} of ${_waDataset.orders.length} orders`;
        }

        // For "totals" cards: when a filter is active, totals reflect FILTERED set,
        // not the WooCommerce-wide store totals. We synthesize a totals-shape object.
        const isFiltered = _waDateRange && (_waDateRange.from || _waDateRange.to);
        const totalsForCards = isFiltered
            ? { orders: filteredOrders.length, customers: (_waApiTotals && _waApiTotals.customers) || customers.total }
            : _waApiTotals;

        const safeRender = (name, fn) => {
            try { fn(); }
            catch (e) { console.error('[Websites Analytics] ' + name + ' failed:', e); updateDebug('⚠ ' + name + ' error: ' + e.message); }
        };
        safeRender('Cards',        () => renderCards(filteredOrders, totalsForCards));
        safeRender('Tracking',     () => renderTracking(tracking));
        safeRender('RevenueChart', () => renderRevenueChart(filteredOrders));
        safeRender('ProductsChart',() => renderProductsChart(products));
        safeRender('Sources',      () => renderSourcesChart(sources, tracking.todaySessions));
        safeRender('Devices',      () => renderDevicesChart(devices));
        safeRender('Activity',     () => renderActivityFeed(activities));
        safeRender('TopPages',     () => renderTopPages(topPages));
        safeRender('OrdersTable',  () => renderOrdersTable(filteredOrders));
        safeRender('Funnel',       () => renderFunnel(filteredOrders));
        safeRender('Abandoned',    () => renderAbandoned(filteredOrders));
        safeRender('Geography',    () => renderGeography(geography));
        safeRender('TopProducts',  () => renderTopProductsList(products));
        safeRender('Customers',    () => renderCustomers(customers));
    }

    async function _waLoadAllInner() {
        let orders, products, customers;

        const badge = document.getElementById('waDataBadge');
        try {
            const payload = await waFetchAll();
            if (payload.fallback || payload.error) {
                throw new Error(payload.error || 'API keys not configured');
            }
            orders    = Array.isArray(payload.orders)    ? payload.orders    : [];
            products  = Array.isArray(payload.products)  ? payload.products  : [];
            const rawCustomers = Array.isArray(payload.customers) ? payload.customers : [];
            if (orders.length === 0 && products.length === 0) throw new Error('Empty response from API');

            // Use true totals from the store (X-WP-Total headers) for accurate counts
            _waApiTotals = payload.totals || { orders: orders.length, customers: rawCustomers.length };
            const totalCustomers = _waApiTotals.customers || rawCustomers.length;
            const newCustomers = _waApiTotals.new_customers || Math.round(totalCustomers * 0.15);
            customers = {
                total: totalCustomers,
                new: newCustomers,
                returning: totalCustomers - newCustomers
            };
            if (badge) {
                badge.textContent = '● Live WooCommerce Data';
                badge.style.background = '#e8f5e9';
                badge.style.color = '#2e7d32';
            }
        } catch (err) {
            orders = mockOrders(50);
            products = mockProducts();
            customers = mockCustomers();
            showBanner('Showing mock data — ' + err.message + '. Add API keys to .env (WOOCOMMERCE_AR_CONSUMER_KEY / WOOCOMMERCE_AR_CONSUMER_SECRET) to see live WooCommerce data.');
            if (badge) {
                badge.textContent = '⚠ Mock Data';
                badge.style.background = '#fff4e5';
                badge.style.color = '#8a6100';
            }
        }

        products.sort((a, b) => (parseFloat(b.price) * (b.total_sales || 0)) - (parseFloat(a.price) * (a.total_sales || 0)));

        // Generate tracking data (always mock — no WooCommerce endpoint for this)
        const tracking = mockTracking();
        const sources = mockSources();
        const devices = mockDevices();
        const activities = mockActivityFeed();
        const topPages = mockTopPages();
        const geography = mockGeography();

        // Cache full dataset for client-side date filtering — no re-fetch needed
        _waDataset = { orders, products, customers, tracking, sources, devices, activities, topPages, geography };

        // Initialize default range from the dropdown's current value (default "30d")
        const presetEl = document.getElementById('waDatePreset');
        const initialPreset = presetEl ? presetEl.value : '30d';
        _waDateRange = buildDateRange(initialPreset);

        renderAll();
    }

    // Date filter: preset dropdown
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'waDatePreset') {
            const preset = e.target.value;
            const fromEl = document.getElementById('waDateFrom');
            const toEl   = document.getElementById('waDateTo');
            const applyEl= document.getElementById('waDateApply');
            if (preset === 'custom') {
                // Reveal the custom-range inputs; don't apply until user clicks Apply
                fromEl.style.display = '';
                toEl.style.display = '';
                applyEl.style.display = '';
                return;
            }
            fromEl.style.display = 'none';
            toEl.style.display = 'none';
            applyEl.style.display = 'none';
            _waDateRange = buildDateRange(preset);
            renderAll();
        }
    });

    // Date filter: Apply button for custom range
    document.addEventListener('click', function(e) {
        if (e.target && (e.target.id === 'waDateApply' || e.target.closest('#waDateApply'))) {
            const from = document.getElementById('waDateFrom').value;
            const to   = document.getElementById('waDateTo').value;
            if (!from && !to) {
                alert('Please pick at least a "from" or "to" date.');
                return;
            }
            _waDateRange = buildDateRange('custom', from, to);
            renderAll();
        }
    });

    // Orders table interactivity: search + pagination
    document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'waOrdersSearch') {
            _waSearchTerm = e.target.value;
            _waCurrentPage = 1;
            renderOrdersPage();
        }
    });
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'waOrdersPrev') {
            _waCurrentPage = Math.max(1, _waCurrentPage - 1);
            renderOrdersPage();
        } else if (e.target && e.target.id === 'waOrdersNext') {
            _waCurrentPage++;
            renderOrdersPage();
        }
    });

    // Auto-refresh live visitor count every 15s for a "live" feel
    setInterval(function() {
        const el = document.getElementById('waLiveVisitors');
        if (!el) return;
        const curr = parseInt(el.textContent.replace(/[^0-9]/g, '')) || 20;
        const delta = Math.floor(Math.random() * 7) - 3; // -3 to +3
        const next = Math.max(8, Math.min(80, curr + delta));
        el.textContent = numFmt.format(next);
    }, 15000);

    // ---------- CRM Tab (Leads / Deals / Purchases / Installments / Meetings) ----------
    let _waCrmPipelinesPopulated = false;
    let _waCrmCurrentPipeline = 'all';

    async function waLoadCrm(pipelineValue) {
        const siteKey = WA_CONFIG.site_key || 'arabic';
        const badge = document.getElementById('waCrmBadge');
        const selected = (pipelineValue !== undefined && pipelineValue !== null) ? pipelineValue : _waCrmCurrentPipeline;
        const pipeQuery = (selected && selected !== 'all') ? '&pipeline=' + encodeURIComponent(selected) : '';
        if (badge) {
            badge.textContent = 'Loading…';
            badge.style.background = '';
            badge.style.color = '';
        }
        try {
            const res = await fetch('/websites/crm/' + encodeURIComponent(siteKey) + '?_=' + Date.now() + pipeQuery, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            populateCrmPipelineDropdown(data.pipelines || []);
            renderCrmAll(data);
            if (badge) {
                badge.textContent = '● ' + ((data.kpis && data.kpis.leads && data.kpis.leads.total) || 0) + ' leads · '
                    + ((data.kpis && data.kpis.deals && data.kpis.deals.total) || 0) + ' deals';
                badge.style.background = '#e8f5e9';
                badge.style.color = '#2e7d32';
            }
        } catch (err) {
            console.error('[CRM Tab] failed:', err);
            if (badge) {
                badge.textContent = '⚠ ' + err.message;
                badge.style.background = '#fdecea';
                badge.style.color = '#d32f2f';
            }
            ['waCrmLeadsBody','waCrmDealsBody','waCrmMeetingsBody','waCrmOverdueBody'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = '<tr><td colspan="5" class="wa-loading">' + escapeHtml(err.message) + '</td></tr>';
            });
        }
    }

    function populateCrmPipelineDropdown(pipelines) {
        if (_waCrmPipelinesPopulated) return; // only first load — preserves user's selection on re-fetch
        const sel = document.getElementById('waCrmPipeline');
        if (!sel) return;
        // Keep the "All pipelines" option; append the rest from API
        pipelines.forEach(p => {
            const opt = document.createElement('option');
            opt.value = String(p.id);
            opt.textContent = p.name;
            sel.appendChild(opt);
        });
        _waCrmPipelinesPopulated = true;
    }

    // Dropdown change → re-fetch with selected pipeline
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'waCrmPipeline') {
            _waCrmCurrentPipeline = e.target.value;
            waLoadCrm(_waCrmCurrentPipeline);
        }
    });

    // chart instances — destroyed and recreated on each CRM refresh
    let _waTrendChart = null;
    let _waStageChart = null;

    function renderCrmAll(data) {
        const k = data.kpis || {};

        // ── TODAY'S SNAPSHOT ──────────────────────────────────────────────
        const td = data.today || {};
        const tw = data.this_week || {};
        document.getElementById('waTodayLeads').textContent = td.leads || 0;
        document.getElementById('waTodayLeadsWeek').textContent = (tw.leads || 0) + ' this week';
        document.getElementById('waTodayDealsWon').textContent = td.deals_won || 0;
        document.getElementById('waTodayDealsWeek').textContent = (tw.deals_won || 0) + ' this week';
        document.getElementById('waTodayMeetings').textContent = td.meetings || 0;
        document.getElementById('waTodayMeetingsSubtitle').textContent = '';
        document.getElementById('waTodayInstDue').textContent = td.inst_due_count || 0;
        document.getElementById('waTodayInstDueAmt').textContent =
            td.inst_due_count ? currencyFmt.format(td.inst_due_amount || 0) : 'None due today ✓';

        // ── PERFORMANCE METRICS ───────────────────────────────────────────
        const r = data.rates || {};
        document.getElementById('waRateConversion').textContent = (r.conversion || 0) + '%';
        document.getElementById('waRateWin').textContent = (r.win_rate || 0) + '%';
        document.getElementById('waRateAvgDeal').textContent = currencyFmt.format(r.avg_deal || 0);

        const inst = k.installments || {};
        document.getElementById('waRateUnpaid').textContent = currencyFmt.format(inst.unpaid_amount || 0);
        document.getElementById('waRateUnpaidDetail').textContent =
            (inst.overdue || 0) + ' overdue · ' + (inst.unpaid || 0) + ' unpaid';

        document.getElementById('waRateWeekRev').textContent = currencyFmt.format(tw.revenue || 0);

        // ── OVERALL KPI CARDS ─────────────────────────────────────────────
        const leads = k.leads || {};
        document.getElementById('waCrmLeadsTotal').textContent = numFmt.format(leads.total || 0);
        document.getElementById('waCrmLeadsNew').textContent = '+ ' + numFmt.format(leads.new_this_month || 0) + ' this month';

        const deals = k.deals || {};
        document.getElementById('waCrmDealsTotal').textContent = numFmt.format(deals.total || 0);
        document.getElementById('waCrmDealsBreakdown').textContent =
            (deals.active || 0) + ' active · ' + (deals.won || 0) + ' won · ' + (deals.loss || 0) + ' lost';

        const dp = k.deal_purchases || {};
        document.getElementById('waCrmPurchasesCount').textContent = numFmt.format(dp.count || 0);
        document.getElementById('waCrmPurchasesRevenue').textContent =
            currencyFmt.format(dp.revenue || 0) + ' · ' + currencyFmt.format(dp.due || 0) + ' due';

        document.getElementById('waCrmInstallmentsUnpaid').textContent = numFmt.format(inst.total || 0);
        document.getElementById('waCrmInstallmentsOverdue').textContent =
            (inst.paid || 0) + ' paid · ' + (inst.unpaid || 0) + ' unpaid · ' + (inst.overdue || 0) + ' overdue';

        const sm = k.sales_meetings || {};
        document.getElementById('waCrmMeetingsUpcoming').textContent = numFmt.format(sm.total || 0);
        document.getElementById('waCrmMeetingsLast30').textContent =
            (sm.upcoming || 0) + ' upcoming · ' + (sm.last_30d || 0) + ' last 30d';

        // ── CHARTS ────────────────────────────────────────────────────────
        // Monthly Trend
        const trend = Array.isArray(data.monthly_trend) ? data.monthly_trend : [];
        const trendCtx = document.getElementById('waCrmTrendChart');
        if (trendCtx) {
            if (_waTrendChart) { _waTrendChart.destroy(); _waTrendChart = null; }
            _waTrendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trend.map(m => m.month),
                    datasets: [
                        {
                            label: 'Leads',
                            data: trend.map(m => m.leads),
                            borderColor: '#1976d2', backgroundColor: 'rgba(25,118,210,.1)',
                            fill: true, tension: .35, pointRadius: 4, pointHoverRadius: 6,
                        },
                        {
                            label: 'Deals Won',
                            data: trend.map(m => m.won),
                            borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,.08)',
                            fill: true, tension: .35, pointRadius: 4, pointHoverRadius: 6,
                        },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { font: { size: 12 } } } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // Leads by Stage horizontal bar
        const stageData = Array.isArray(data.leads_by_stage) ? data.leads_by_stage : [];
        const stageCtx = document.getElementById('waCrmStageChart');
        if (stageCtx) {
            if (_waStageChart) { _waStageChart.destroy(); _waStageChart = null; }
            const palette = ['#1976d2','#2e7d32','#e65100','#6a1b9a','#00838f','#c62828','#37474f','#f9a825'];
            _waStageChart = new Chart(stageCtx, {
                type: 'bar',
                data: {
                    labels: stageData.map(s => s.stage),
                    datasets: [{
                        label: 'Leads',
                        data: stageData.map(s => s.count),
                        backgroundColor: stageData.map((_, i) => palette[i % palette.length] + 'cc'),
                        borderRadius: 5,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // ── TOP PERFORMERS ────────────────────────────────────────────────
        const perfBody = document.getElementById('waTopPerformersBody');
        perfBody.replaceChildren();
        const perf = Array.isArray(data.top_performers) ? data.top_performers : [];
        if (perf.length === 0) {
            perfBody.innerHTML = '<tr><td colspan="5" class="wa-loading">No data yet</td></tr>';
        } else {
            perf.forEach((p, idx) => {
                const tr = document.createElement('tr');
                const rankTd = document.createElement('td');
                rankTd.textContent = idx + 1;
                rankTd.style.color = idx < 3 ? ['#f9a825','#78909c','#8d6e63'][idx] : '#5b6b82';
                rankTd.style.fontWeight = '700';
                tr.appendChild(rankTd);
                tr.appendChild(makeCell(p.name || '—', true));

                const wonTd = document.createElement('td');
                wonTd.style.textAlign = 'center';
                const wonB = document.createElement('b');
                wonB.style.color = '#2e7d32';
                wonB.textContent = p.won;
                wonTd.appendChild(wonB);
                tr.appendChild(wonTd);

                const lostTd = document.createElement('td');
                lostTd.style.textAlign = 'center';
                lostTd.style.color = '#c62828';
                lostTd.textContent = p.lost;
                tr.appendChild(lostTd);

                const totTd = document.createElement('td');
                totTd.style.textAlign = 'center';
                totTd.textContent = p.total;
                tr.appendChild(totTd);

                perfBody.appendChild(tr);
            });
        }

        // ── RECENT DEAL PURCHASES ─────────────────────────────────────────
        const purchBody = document.getElementById('waRecentPurchasesBody');
        purchBody.replaceChildren();
        const rp = Array.isArray(data.recent_purchases) ? data.recent_purchases : [];
        if (rp.length === 0) {
            purchBody.innerHTML = '<tr><td colspan="5" class="wa-loading">No purchases yet</td></tr>';
        } else {
            rp.forEach(p => {
                const tr = document.createElement('tr');
                const dealCell = document.createElement('td');
                const db = document.createElement('b');
                db.textContent = p.deal || '—';
                dealCell.appendChild(db);
                if (p.lead) {
                    const sub = document.createElement('div');
                    sub.style.cssText = 'font-size:11px; color:#5b6b82; margin-top:1px;';
                    sub.textContent = p.lead;
                    dealCell.appendChild(sub);
                }
                tr.appendChild(dealCell);

                const priceTd = document.createElement('td');
                priceTd.textContent = currencyFmt.format(p.price || 0);
                tr.appendChild(priceTd);

                const paidTd = document.createElement('td');
                paidTd.style.color = '#2e7d32';
                paidTd.textContent = currencyFmt.format(p.paid || 0);
                tr.appendChild(paidTd);

                const dueTd = document.createElement('td');
                dueTd.style.color = p.due > 0 ? '#c62828' : '#2e7d32';
                dueTd.style.fontWeight = p.due > 0 ? '700' : 'normal';
                dueTd.textContent = p.due > 0 ? currencyFmt.format(p.due) : '✓';
                tr.appendChild(dueTd);

                tr.appendChild(makeCell(p.date || '—'));
                purchBody.appendChild(tr);
            });
        }

        // ── TODAY'S MEETINGS PANEL ────────────────────────────────────────
        const todayMtgs = Array.isArray(data.today_meetings) ? data.today_meetings : [];
        const todayPanel = document.getElementById('waTodayMeetingsPanel');
        const todayBody  = document.getElementById('waTodayMeetingsBody');
        const todayBadge = document.getElementById('waTodayMeetingsBadge');
        if (todayBody) todayBody.replaceChildren();
        if (todayMtgs.length === 0) {
            if (todayPanel) todayPanel.style.display = 'none';
        } else {
            if (todayPanel) todayPanel.style.display = '';
            if (todayBadge) todayBadge.textContent = todayMtgs.length + ' today';
            todayMtgs.forEach(m => {
                const tr = document.createElement('tr');
                tr.appendChild(makeCell(m.title || '—', true));
                tr.appendChild(makeCell(m.lead || '—'));
                tr.appendChild(makeCell(m.deal || '—'));
                tr.appendChild(makeCell(m.type || '—'));
                tr.appendChild(makeCell(m.time || '—'));
                tr.appendChild(makeCell(m.duration ? m.duration + ' min' : '—'));

                const linkTd = document.createElement('td');
                if (m.link) {
                    const a = document.createElement('a');
                    a.href = m.link; a.target = '_blank'; a.rel = 'noopener';
                    a.style.cssText = 'color:var(--wa-primary); font-size:12px;';
                    a.textContent = 'Join →';
                    linkTd.appendChild(a);
                }
                tr.appendChild(linkTd);
                todayBody.appendChild(tr);
            });
        }

        // ── RECENT LEADS ──────────────────────────────────────────────────
        const leadsBody = document.getElementById('waCrmLeadsBody');
        leadsBody.replaceChildren();
        const rl = Array.isArray(data.recent_leads) ? data.recent_leads : [];
        if (rl.length === 0) {
            leadsBody.innerHTML = '<tr><td colspan="5" class="wa-loading">No leads yet</td></tr>';
        } else {
            rl.forEach(l => {
                const tr = document.createElement('tr');
                tr.appendChild(makeCell(l.name || '—', true));
                tr.appendChild(makeCell(l.phone || '—'));
                tr.appendChild(makeCell(l.country || '—'));
                tr.appendChild(makeCell(l.stage || '—'));
                tr.appendChild(makeCell(l.date || '—'));
                leadsBody.appendChild(tr);
            });
        }

        // ── RECENT DEALS ──────────────────────────────────────────────────
        const dealsBody = document.getElementById('waCrmDealsBody');
        dealsBody.replaceChildren();
        const rd = Array.isArray(data.recent_deals) ? data.recent_deals : [];
        if (rd.length === 0) {
            dealsBody.innerHTML = '<tr><td colspan="4" class="wa-loading">No deals yet</td></tr>';
        } else {
            rd.forEach(d => {
                const tr = document.createElement('tr');
                tr.appendChild(makeCell(d.name || '—', true));
                tr.appendChild(makeCell(d.lead || '—'));

                const tdStatus = document.createElement('td');
                const badge = document.createElement('span');
                const s = (d.status || '').toLowerCase();
                badge.className = 'wa-status ' + (s === 'won' ? 'wa-status-completed' : s === 'loss' ? 'wa-status-cancelled' : 'wa-status-processing');
                badge.textContent = d.status || '—';
                tdStatus.appendChild(badge);
                tr.appendChild(tdStatus);

                tr.appendChild(makeCell(d.datetime || '—'));
                dealsBody.appendChild(tr);
            });
        }

        // ── UPCOMING MEETINGS ─────────────────────────────────────────────
        const meetingsBody = document.getElementById('waCrmMeetingsBody');
        meetingsBody.replaceChildren();
        const um = Array.isArray(data.upcoming_meetings) ? data.upcoming_meetings : [];
        document.getElementById('waCrmMeetingsBadge').textContent = um.length + ' upcoming';
        if (um.length === 0) {
            meetingsBody.innerHTML = '<tr><td colspan="4" class="wa-loading">No upcoming meetings</td></tr>';
        } else {
            um.forEach(m => {
                const tr = document.createElement('tr');
                tr.appendChild(makeCell(m.title || '—', true));
                tr.appendChild(makeCell(m.lead || '—'));
                tr.appendChild(makeCell(m.date || '—'));
                tr.appendChild(makeCell(m.time || '—'));
                meetingsBody.appendChild(tr);
            });
        }

        // ── OVERDUE INSTALLMENTS ──────────────────────────────────────────
        const overdueBody = document.getElementById('waCrmOverdueBody');
        overdueBody.replaceChildren();
        const oi = Array.isArray(data.overdue_installments) ? data.overdue_installments : [];
        document.getElementById('waCrmOverdueBadge').textContent = oi.length + ' overdue';
        if (oi.length === 0) {
            overdueBody.innerHTML = '<tr><td colspan="3" class="wa-loading" style="color:#2e7d32;">✓ No overdue installments</td></tr>';
        } else {
            oi.forEach(i => {
                const tr = document.createElement('tr');
                const label = (i.deal ? i.deal : '—') + (i.lead ? ' / ' + i.lead : '');
                tr.appendChild(makeCell(label, true));

                const tdAmt = document.createElement('td');
                const b = document.createElement('b');
                b.style.color = 'var(--wa-danger)';
                b.textContent = currencyFmt.format(i.effective || i.amount || 0);
                tdAmt.appendChild(b);
                tr.appendChild(tdAmt);

                tr.appendChild(makeCell(i.date || '—'));
                overdueBody.appendChild(tr);
            });
        }

        // ── EXCLUDED MEETINGS ─────────────────────────────────────────────
        const exPanel = document.getElementById('waCrmExcludedPanel');
        const exBody  = document.getElementById('waCrmExcludedBody');
        const exBadge = document.getElementById('waCrmExcludedBadge');
        const ex = Array.isArray(data.excluded_meetings) ? data.excluded_meetings : [];
        if (exBody) exBody.replaceChildren();
        if (ex.length === 0) {
            if (exPanel) exPanel.style.display = 'none';
        } else {
            if (exPanel) exPanel.style.display = '';
            if (exBadge) exBadge.textContent = ex.length + ' excluded';
            ex.forEach(m => {
                const tr = document.createElement('tr');
                const tdId = document.createElement('td');
                const idB = document.createElement('b');
                idB.textContent = '#' + m.id;
                tdId.appendChild(idB);
                tr.appendChild(tdId);
                tr.appendChild(makeCell(m.title || '—'));
                tr.appendChild(makeCell(m.lead_name ? (m.lead_name + ' (#' + m.lead_id + ')') : (m.lead_id ? '#' + m.lead_id + ' (missing)' : '—')));
                tr.appendChild(makeCell(m.deal_name ? (m.deal_name + ' (#' + m.deal_id + ')') : (m.deal_id ? '#' + m.deal_id + ' (missing)' : '—')));
                tr.appendChild(makeCell(m.date || '—'));
                const tdReason = document.createElement('td');
                const rs = document.createElement('span');
                rs.style.cssText = 'color:#8a6100; font-size:12px;';
                rs.textContent = m.reason || '—';
                tdReason.appendChild(rs);
                tr.appendChild(tdReason);
                const tdLink = document.createElement('td');
                const a = document.createElement('a');
                a.href = '/sales-meetings/' + m.id + '/edit';
                a.target = '_blank'; a.rel = 'noopener';
                a.style.cssText = 'color:var(--wa-primary); font-size:12px;';
                a.textContent = 'Open →';
                tdLink.appendChild(a);
                tr.appendChild(tdLink);
                exBody.appendChild(tr);
            });
        }
    }

    function makeCell(text, bold) {
        const td = document.createElement('td');
        if (bold) {
            const b = document.createElement('b');
            b.textContent = text;
            td.appendChild(b);
        } else {
            td.textContent = text;
        }
        return td;
    }

    // ────────────────────────────────────────────────────────────────────
    // LearnPress Orders Tab (bridged from WordPress via /websites/learnpress)
    // ────────────────────────────────────────────────────────────────────
    let _waLpTrendChart  = null;
    let _waLpStatusChart = null;

    async function waLoadLp() {
        const badge = document.getElementById('waLpBadge');
        if (badge) { badge.textContent = 'Loading…'; badge.style.background=''; badge.style.color=''; }
        try {
            const res = await fetch('/websites/learnpress?_=' + Date.now(), {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            // Bridge not configured → show the setup banner and stop
            if (data.configured === false) {
                const panel = document.getElementById('waLpSetupPanel');
                const detail = document.getElementById('waLpSetupDetail');
                if (panel) panel.style.display = '';
                if (detail) detail.textContent = data.error || '';
                if (badge) {
                    badge.textContent = '⚙ Not configured';
                    badge.style.background = '#fff4e5';
                    badge.style.color = '#8a6100';
                }
                return;
            }

            // Bridge reachable but errored upstream
            if (data.error) {
                if (badge) {
                    badge.textContent = '⚠ ' + data.error;
                    badge.style.background = '#fdecea';
                    badge.style.color = '#d32f2f';
                }
                return;
            }

            renderLpAll(data);

            const totals = data.totals || {};
            if (badge) {
                badge.textContent = '● ' + numFmt.format(totals.all_count || 0) + ' orders · ' + currencyFmt.format(totals.all_revenue || 0);
                badge.style.background = '#e8f5e9';
                badge.style.color = '#2e7d32';
            }
        } catch (err) {
            console.error('[LP Tab] failed:', err);
            if (badge) {
                badge.textContent = '⚠ ' + err.message;
                badge.style.background = '#fdecea';
                badge.style.color = '#d32f2f';
            }
        }
    }

    function renderLpAll(data) {
        const t = data.totals || {};
        const sc = data.status_counts || {};

        // KPI cards
        document.getElementById('waLpTodayRev').textContent = currencyFmt.format(t.today_revenue || 0);
        document.getElementById('waLpTodayCount').textContent = numFmt.format(t.today_count || 0) + ' orders';
        document.getElementById('waLpWeekRev').textContent = currencyFmt.format(t.week_revenue || 0);
        document.getElementById('waLpWeekCount').textContent = numFmt.format(t.week_count || 0) + ' orders';
        document.getElementById('waLpMonthRev').textContent = currencyFmt.format(t.month_revenue || 0);
        document.getElementById('waLpMonthCount').textContent = numFmt.format(t.month_count || 0) + ' orders';
        document.getElementById('waLpAllRev').textContent = currencyFmt.format(t.all_revenue || 0);
        document.getElementById('waLpAllCount').textContent = numFmt.format(t.all_count || 0) + ' orders lifetime';

        const pending = (sc.pending || 0) + (sc.processing || 0) + (sc.failed || 0);
        document.getElementById('waLpPending').textContent = numFmt.format(pending);
        document.getElementById('waLpStatusBreakdown').textContent =
            (sc.completed || 0) + ' done · ' + (sc.pending || 0) + ' pending · ' + (sc.failed || 0) + ' failed';

        // Monthly trend line chart
        const trend = Array.isArray(data.monthly_trend) ? data.monthly_trend : [];
        const trendCtx = document.getElementById('waLpTrendChart');
        if (trendCtx) {
            if (_waLpTrendChart) { _waLpTrendChart.destroy(); _waLpTrendChart = null; }
            _waLpTrendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trend.map(m => m.month),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: trend.map(m => m.revenue),
                            borderColor: '#6a1b9a',
                            backgroundColor: 'rgba(106,27,154,.12)',
                            fill: true, tension: .35, pointRadius: 4, pointHoverRadius: 6,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Orders',
                            data: trend.map(m => m.count),
                            borderColor: '#e65100',
                            backgroundColor: 'rgba(230,81,0,.08)',
                            fill: false, tension: .35, pointRadius: 4, pointHoverRadius: 6,
                            yAxisID: 'y1',
                        },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { font: { size: 12 } } } },
                    scales: {
                        y:  { type: 'linear', position: 'left',  beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 } } },
                        y1: { type: 'linear', position: 'right', beginAtZero: true, grid: { display: false }, ticks: { font: { size: 11 } } },
                        x:  { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }

        // Status donut
        const statusCtx = document.getElementById('waLpStatusChart');
        if (statusCtx) {
            if (_waLpStatusChart) { _waLpStatusChart.destroy(); _waLpStatusChart = null; }
            const keys = Object.keys(sc);
            const colorMap = {
                completed: '#2e7d32', processing: '#1565c0', pending: '#e65100',
                cancelled: '#757575', failed: '#c62828', 'on-hold': '#6a1b9a'
            };
            _waLpStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: keys,
                    datasets: [{
                        data: keys.map(k => sc[k]),
                        backgroundColor: keys.map(k => colorMap[k] || '#90a4ae'),
                        borderWidth: 2, borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'right', labels: { font: { size: 12 }, padding: 10 } } },
                    cutout: '60%',
                }
            });
        }

        // Top courses table
        const cbody = document.getElementById('waLpCoursesBody');
        cbody.replaceChildren();
        const courses = Array.isArray(data.top_courses) ? data.top_courses : [];
        if (courses.length === 0) {
            cbody.innerHTML = '<tr><td colspan="4" class="wa-loading">No course sales yet</td></tr>';
        } else {
            courses.forEach((c, idx) => {
                const tr = document.createElement('tr');
                const rankTd = document.createElement('td');
                rankTd.textContent = idx + 1;
                rankTd.style.color = idx < 3 ? ['#f9a825','#78909c','#8d6e63'][idx] : '#5b6b82';
                rankTd.style.fontWeight = '700';
                tr.appendChild(rankTd);
                tr.appendChild(makeCell(c.name || '—', true));
                const sTd = document.createElement('td');
                sTd.style.textAlign = 'center';
                const sB = document.createElement('b');
                sB.style.color = '#2e7d32';
                sB.textContent = numFmt.format(c.sales || 0);
                sTd.appendChild(sB);
                tr.appendChild(sTd);
                tr.appendChild(makeCell(currencyFmt.format(c.revenue || 0)));
                cbody.appendChild(tr);
            });
        }

        // Recent orders table
        const obody = document.getElementById('waLpOrdersBody');
        obody.replaceChildren();
        const orders = Array.isArray(data.recent_orders) ? data.recent_orders : [];
        if (orders.length === 0) {
            obody.innerHTML = '<tr><td colspan="6" class="wa-loading">No orders</td></tr>';
        } else {
            orders.forEach(o => {
                const tr = document.createElement('tr');
                const idTd = document.createElement('td');
                const idB = document.createElement('b');
                idB.textContent = '#' + o.id;
                idTd.appendChild(idB);
                tr.appendChild(idTd);

                const userTd = document.createElement('td');
                if (o.user && o.user.name) {
                    const nb = document.createElement('div');
                    nb.style.fontWeight = '600';
                    nb.textContent = o.user.name;
                    userTd.appendChild(nb);
                    if (o.user.email) {
                        const em = document.createElement('div');
                        em.style.cssText = 'font-size:11px; color:#5b6b82;';
                        em.textContent = o.user.email;
                        userTd.appendChild(em);
                    }
                } else {
                    userTd.textContent = '—';
                }
                tr.appendChild(userTd);

                const courseTd = document.createElement('td');
                if (Array.isArray(o.items) && o.items.length) {
                    courseTd.textContent = o.items[0].name + (o.items.length > 1 ? ' (+' + (o.items.length - 1) + ')' : '');
                } else { courseTd.textContent = '—'; }
                tr.appendChild(courseTd);

                const totTd = document.createElement('td');
                const tb = document.createElement('b');
                tb.textContent = (o.currency ? o.currency + ' ' : '') + numFmt.format(o.total || 0);
                totTd.appendChild(tb);
                tr.appendChild(totTd);

                const stTd = document.createElement('td');
                const stB = document.createElement('span');
                const s = (o.status || '').toLowerCase();
                stB.className = 'wa-status ' + (s === 'completed' ? 'wa-status-completed' : (s === 'cancelled' || s === 'failed') ? 'wa-status-cancelled' : 'wa-status-processing');
                stB.textContent = o.status || '—';
                stTd.appendChild(stB);
                tr.appendChild(stTd);

                tr.appendChild(makeCell((o.date || '').substring(0, 16) || '—'));
                obody.appendChild(tr);
            });
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // CSV download helper — turns an array of rows + headers into a CSV file
    // and triggers a browser download. Excel/Numbers open it natively.
    // ────────────────────────────────────────────────────────────────────
    function waCsvEscape(v) {
        if (v === null || v === undefined) return '';
        const s = String(v);
        // Quote if contains comma, quote, newline, or leading/trailing space
        if (/[",\n\r]/.test(s) || /^\s|\s$/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }
    function waDownloadCsv(filename, headers, rows) {
        const lines = [];
        lines.push(headers.map(waCsvEscape).join(','));
        rows.forEach(r => {
            lines.push(r.map(waCsvEscape).join(','));
        });
        // UTF-8 BOM so Excel reads Arabic correctly
        const blob = new Blob(["﻿" + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
    }

    // ────────────────────────────────────────────────────────────────────
    // Course Activations Tab (period-filterable list of completed orders × courses)
    // ────────────────────────────────────────────────────────────────────
    let _waActPeriod = 'this_month';
    let _waActQuery  = '';
    let _waActSearchTimer = null;
    let _waActRowsCache = [];      // last loaded activations (for export)
    let _waActMetaCache = {};      // last loaded meta (period, date range, totals)

    async function waLoadActivations() {
        const badge = document.getElementById('waActBadge');
        if (badge) { badge.textContent = 'Loading…'; badge.style.background = ''; badge.style.color = ''; }

        const params = new URLSearchParams();
        params.set('period', _waActPeriod);
        params.set('per_page', '500');
        if (_waActQuery) params.set('q', _waActQuery);

        try {
            const res = await fetch('/websites/learnpress/activations?' + params.toString() + '&_=' + Date.now(), {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            if (data.configured === false || data.error) {
                if (badge) {
                    badge.textContent = '⚠ ' + (data.error || 'Bridge not configured');
                    badge.style.background = '#fff4e5';
                    badge.style.color = '#8a6100';
                }
                const tbody = document.getElementById('waActBody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="wa-loading">' + escapeHtml(data.error || 'Not configured') + '</td></tr>';
                return;
            }
            renderActivations(data);
            if (badge) {
                badge.textContent = '● ' + numFmt.format(data.total || 0) + ' rows';
                badge.style.background = '#e8f5e9';
                badge.style.color = '#2e7d32';
            }
        } catch (err) {
            console.error('[Activations] failed:', err);
            if (badge) {
                badge.textContent = '⚠ ' + err.message;
                badge.style.background = '#fdecea';
                badge.style.color = '#d32f2f';
            }
        }
    }

    function renderActivations(data) {
        // Cache for CSV export
        _waActRowsCache = Array.isArray(data.activations) ? data.activations : [];
        _waActMetaCache = {
            period:    data.period || _waActPeriod,
            date_from: data.date_from || '',
            date_to:   data.date_to || '',
            total:     data.total || 0,
            students:  data.unique_students || 0,
            revenue:   data.sum_amount || 0,
        };

        const total = data.total || 0;
        const sum = data.sum_amount || 0;
        const students = data.unique_students || 0;
        const avg = total > 0 ? (sum / total) : 0;

        document.getElementById('waActTotal').textContent = numFmt.format(total);
        document.getElementById('waActStudents').textContent = numFmt.format(students);
        document.getElementById('waActRevenue').textContent = currencyFmt.format(sum);
        document.getElementById('waActAvg').textContent = currencyFmt.format(avg);
        document.getElementById('waActPeriodLabel').textContent =
            (data.date_from || '') + ' → ' + (data.date_to || '');
        document.getElementById('waActListBadge').textContent =
            (data.activations && data.activations.length ? data.activations.length : 0) + ' shown';

        const tbody = document.getElementById('waActBody');
        tbody.replaceChildren();
        const rows = Array.isArray(data.activations) ? data.activations : [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="wa-loading">No activations in this period</td></tr>';
            return;
        }
        rows.forEach((a, idx) => {
            const tr = document.createElement('tr');

            const idxTd = document.createElement('td');
            idxTd.textContent = idx + 1;
            idxTd.style.color = '#5b6b82';
            tr.appendChild(idxTd);

            const stuTd = document.createElement('td');
            if (a.student && a.student.name) {
                const nb = document.createElement('div');
                nb.style.fontWeight = '600';
                nb.textContent = a.student.name;
                stuTd.appendChild(nb);
                if (a.student.email) {
                    const em = document.createElement('div');
                    em.style.cssText = 'font-size:11px; color:#5b6b82;';
                    em.textContent = a.student.email;
                    stuTd.appendChild(em);
                }
            } else {
                stuTd.textContent = a.student && a.student.email ? a.student.email : '—';
            }
            tr.appendChild(stuTd);

            tr.appendChild(makeCell(a.course || '—', true));

            const amtTd = document.createElement('td');
            const amtB = document.createElement('b');
            amtB.style.color = '#2e7d32';
            amtB.textContent = a.amount !== null ? currencyFmt.format(a.amount) : '—';
            amtTd.appendChild(amtB);
            tr.appendChild(amtTd);

            tr.appendChild(makeCell(a.payment_method || '—'));
            tr.appendChild(makeCell((a.date || '').substring(0, 16) || '—'));
            tbody.appendChild(tr);
        });
    }

    // Period dropdown
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'waActPeriod') {
            _waActPeriod = e.target.value;
            waLoadActivations();
        }
    });
    // Search box (debounced)
    document.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'waActSearch') {
            clearTimeout(_waActSearchTimer);
            _waActSearchTimer = setTimeout(function() {
                _waActQuery = e.target.value.trim();
                waLoadActivations();
            }, 400);
        }
    });

    // ────────────────────────────────────────────────────────────────────
    // Export buttons — Activations CSV + WooCommerce Orders CSV
    // ────────────────────────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        // Course Activations export
        if (e.target && e.target.closest && e.target.closest('#waActExport')) {
            e.preventDefault();
            if (!_waActRowsCache || _waActRowsCache.length === 0) {
                alert('No activations to export. Wait for the data to load or change the period.');
                return;
            }
            const headers = ['#', 'Order ID', 'Student Name', 'Student Email', 'Course', 'Course ID', 'Amount', 'Payment Method', 'Date'];
            const rows = _waActRowsCache.map((a, idx) => [
                idx + 1,
                a.order_id || '',
                (a.student && a.student.name) || '',
                (a.student && a.student.email) || '',
                a.course || '',
                a.course_id || '',
                a.amount !== null && a.amount !== undefined ? a.amount : '',
                a.payment_method || '',
                a.date || '',
            ]);
            const periodLabel = _waActMetaCache.period || 'period';
            const dateRange = (_waActMetaCache.date_from || '') + '_to_' + (_waActMetaCache.date_to || '');
            waDownloadCsv('course_activations_' + periodLabel + '_' + dateRange + '.csv', headers, rows);
            return;
        }

        // WooCommerce All Orders export
        if (e.target && e.target.closest && e.target.closest('#waOrdersExport')) {
            e.preventDefault();
            const orders = (typeof _waAllOrders !== 'undefined' && Array.isArray(_waAllOrders)) ? _waAllOrders : [];
            if (orders.length === 0) {
                alert('No orders to export. Wait for the data to load.');
                return;
            }
            const headers = ['Order ID', 'Customer Name', 'Customer Email', 'Total', 'Currency', 'Status', 'Date Created', 'Payment Method'];
            const rows = orders.map(o => {
                const billing = o.billing || {};
                const customer = (billing.first_name || '') + (billing.last_name ? ' ' + billing.last_name : '');
                return [
                    o.id || o.number || '',
                    customer.trim() || (o.customer_name || ''),
                    billing.email || o.customer_email || '',
                    o.total || '',
                    o.currency || '',
                    o.status || '',
                    o.date_created || '',
                    o.payment_method_title || o.payment_method || '',
                ];
            });
            const siteKey = (typeof WA_CONFIG !== 'undefined' && WA_CONFIG.site_key) ? WA_CONFIG.site_key : 'site';
            const stamp = new Date().toISOString().slice(0, 10);
            waDownloadCsv('woocommerce_orders_' + siteKey + '_' + stamp + '.csv', headers, rows);
            return;
        }
    });

    // Load CRM data in parallel with the WooCommerce dashboard
    // (independent fetch — failure on one shouldn't block the other)
    function waBootstrapCrm() {
        waLoadCrm();
    }
    function waBootstrapLp() {
        waLoadLp();
        waLoadActivations();
    }

    // If DOM is already ready (common when Blade push stacks render scripts at end of body),
    // call waLoadAll immediately. Otherwise wait for DOMContentLoaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { waLoadAll(); waBootstrapCrm(); waBootstrapLp(); });
    } else {
        // DOM already parsed — run on next tick to let Chart.js CDN resolve
        setTimeout(function() { waLoadAll(); waBootstrapCrm(); waBootstrapLp(); }, 0);
    }
})();
</script>
@endpush
