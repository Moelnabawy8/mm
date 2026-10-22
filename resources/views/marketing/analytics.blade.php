@extends('layouts.admin')

@section('page-title')
    {{ __('Marketing Analytics') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Analytics') }}</li>
@endsection

@section('action-btn')
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('marketing.analytics', ['range' => 'today']) }}"
           class="btn btn-sm {{ !$isCustom && $dateRange === 'today' ? 'btn-danger' : 'btn-outline-danger' }}">
            <i class="ti ti-broadcast"></i> {{ __('Real-time') }}
        </a>

        <div class="btn-group">
            <a href="{{ route('marketing.analytics', ['range' => 7]) }}" class="btn btn-sm {{ !$isCustom && $dateRange === 7 ? 'btn-primary' : 'btn-outline-primary' }}">7 {{ __('Days') }}</a>
            <a href="{{ route('marketing.analytics', ['range' => 30]) }}" class="btn btn-sm {{ !$isCustom && $dateRange === 30 ? 'btn-primary' : 'btn-outline-primary' }}">30 {{ __('Days') }}</a>
            <a href="{{ route('marketing.analytics', ['range' => 90]) }}" class="btn btn-sm {{ !$isCustom && $dateRange === 90 ? 'btn-primary' : 'btn-outline-primary' }}">90 {{ __('Days') }}</a>
        </div>

        <form action="{{ route('marketing.analytics') }}" method="GET" class="d-flex align-items-center gap-1">
            <input type="date" name="from" value="{{ $fromValue }}" max="{{ date('Y-m-d') }}"
                   class="form-control form-control-sm" style="width: auto;" required>
            <span class="text-muted">–</span>
            <input type="date" name="to" value="{{ $toValue }}" max="{{ date('Y-m-d') }}"
                   class="form-control form-control-sm" style="width: auto;" required>
            <button type="submit" class="btn btn-sm {{ $isCustom ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="ti ti-calendar-search"></i> {{ __('Apply') }}
            </button>
        </form>
    </div>
@endsection

@push('theme-script')
    <script src="{{ asset('assets/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
@endpush

@section('content')
    @php
        // Professional metric sets per platform with WEEKLY targets. Targets are pro-rated
        // to the selected period in JS (weekly × days/7); a tile lights green when it meets
        // its target, red when below. `invert => true` means lower is better (unfollows).
        $fbMetrics = [
            ['key' => 'follows',          'label' => __('Page Follows'),         'icon' => 'ti ti-users',        'color' => '#1877f2', 'target' => 2500],
            ['key' => 'post_reach',       'label' => __('Total Post Reach'),     'icon' => 'ti ti-eye',          'color' => '#16a34a', 'target' => 400000],
            ['key' => 'engagements',      'label' => __('Total Engagements'),    'icon' => 'ti ti-heart',        'color' => '#e11d48', 'target' => 12500],
            ['key' => 'clicks',           'label' => __('Total Clicks'),         'icon' => 'ti ti-click',        'color' => '#9333ea', 'target' => 2500],
            ['key' => 'page_views',       'label' => __('Total Page Views'),     'icon' => 'ti ti-browser',      'color' => '#0891b2', 'target' => 1000000],
            ['key' => 'video_views',      'label' => __('Video/Reels Views'),    'icon' => 'ti ti-video',        'color' => '#d97706', 'target' => 25000],
            ['key' => 'referral_traffic', 'label' => __('Referral Traffic'),     'icon' => 'ti ti-external-link','color' => '#64748b', 'target' => 58.25],
        ];
        $igMetrics = [
            ['key' => 'follows',             'label' => __('Page Follows'),        'icon' => 'ti ti-users',       'color' => '#d6249f', 'target' => 1000],
            ['key' => 'post_reach',          'label' => __('Total Post Reach'),    'icon' => 'ti ti-eye',         'color' => '#16a34a', 'target' => 100000],
            ['key' => 'interactions',        'label' => __('Total Interactions'),  'icon' => 'ti ti-heart',       'color' => '#e11d48', 'target' => 1500],
            ['key' => 'impressions',         'label' => __('Total Impressions'),   'icon' => 'ti ti-chart-bar',   'color' => '#0891b2', 'target' => 561726],
            ['key' => 'clicks',              'label' => __('Total Clicks'),        'icon' => 'ti ti-click',       'color' => '#9333ea', 'target' => 115],
            ['key' => 'page_views',          'label' => __('Total Page Views'),    'icon' => 'ti ti-browser',     'color' => '#2563eb', 'target' => 200000],
            ['key' => 'reels_plays',         'label' => __('Reels Plays'),         'icon' => 'ti ti-player-play', 'color' => '#7c3aed', 'target' => 25000],
            ['key' => 'reels_views',         'label' => __('Reels Views'),         'icon' => 'ti ti-movie',       'color' => '#db2777', 'target' => 16750],
            ['key' => 'stories_impressions', 'label' => __('Stories Impressions'), 'icon' => 'ti ti-circle-dot',  'color' => '#64748b', 'target' => 4500],
            ['key' => 'stories_engagement',  'label' => __('Stories Engagement'),  'icon' => 'ti ti-bolt',        'color' => '#64748b', 'target' => 4750],
            ['key' => 'unfollows',           'label' => __('IG Unfollows'),        'icon' => 'ti ti-user-minus',  'color' => '#64748b', 'target' => 25, 'invert' => true],
        ];
    @endphp

    <style>
        .account-stat-card { border: 1px solid #eceef3; border-radius: .75rem; transition: box-shadow .2s; }
        .account-stat-card:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.08); }
        .account-stat-card .stat-avatar { width: 46px; height: 46px; border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
        .stat-tile { background:#f8f9fc; border:1px solid #eef0f5; border-radius:.6rem; padding:.6rem .5rem; height:100%; transition: background .2s, border-color .2s; }
        .stat-tile .stat-ico { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex:0 0 auto; }
        .stat-tile .stat-val { font-size:1.05rem; font-weight:700; line-height:1.1; }
        .stat-tile .stat-lbl { font-size:.68rem; color:#8a94a6; line-height:1.1; }
        /* target colouring */
        .stat-tile.tile-up   { background:#e9f9ef; border-color:#b7ebc6; }
        .stat-tile.tile-up   .stat-val { color:#15803d; }
        .stat-tile.tile-down { background:#fdecec; border-color:#f6c2c2; }
        .stat-tile.tile-down .stat-val { color:#dc2626; }
        .stat-tile .target-line { font-size:.6rem; color:#aab2c0; line-height:1; }
    </style>

    <div class="row g-3" id="account-cards">
        @foreach($accounts as $account)
            @if(!in_array($account->platform, ['facebook', 'instagram'])) @continue @endif
            @php $metrics = $account->platform === 'instagram' ? $igMetrics : $fbMetrics; @endphp
            <div class="col-xl-4 col-lg-6">
                <div class="card account-stat-card h-100"
                     data-account-id="{{ $account->id }}"
                     data-stats-url="{{ route('marketing.analytics.account-stats', $account->id) }}?{{ http_build_query(request()->only(['range', 'from', 'to'])) }}">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <span class="stat-avatar" style="background: {{ $account->platform_color }}1a; color: {{ $account->platform_color }};">
                                    <i class="{{ $account->platform_icon }}"></i>
                                </span>
                                <div class="ms-2">
                                    <h6 class="mb-0 fw-bold text-truncate" style="max-width: 200px;">{{ $account->account_name }}</h6>
                                    <small class="text-muted">{{ ucfirst($account->platform) }}</small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary stat-reload" title="{{ __('Reload') }}">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>

                        <div class="row g-2">
                            @foreach($metrics as $m)
                                <div class="col-6">
                                    <div class="stat-tile d-flex align-items-center gap-2"
                                         data-target="{{ $m['target'] }}"
                                         data-invert="{{ !empty($m['invert']) ? 1 : 0 }}">
                                        <span class="stat-ico" style="background: {{ $m['color'] }}1a; color: {{ $m['color'] }};">
                                            <i class="{{ $m['icon'] }}"></i>
                                        </span>
                                        <div class="overflow-hidden">
                                            <div class="stat-val" data-stat="{{ $m['key'] }}">—</div>
                                            <div class="stat-lbl text-truncate">{{ $m['label'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <small class="stat-status text-muted">
                                <span class="spinner-border spinner-border-sm" role="status"></span> {{ __('Loading…') }}
                            </small>
                            <small class="text-muted"><i class="ti ti-calendar-event"></i> {{ $rangeLabel }}</small>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Campaign Performance Summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Impressions') }}</th>
                                    <th>{{ __('Clicks') }}</th>
                                    <th>{{ __('Reach') }}</th>
                                    <th>{{ __('Conversions') }}</th>
                                    <th>{{ __('Spend') }}</th>
                                    <th>{{ __('CTR') }}</th>
                                    <th>{{ __('CPC') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $groupedResults = $campaignResults->groupBy(function($item) {
                                        return $item->date->format('Y-m-d');
                                    });
                                @endphp
                                @forelse($groupedResults as $date => $results)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</td>
                                        <td>{{ number_format($results->sum('impressions')) }}</td>
                                        <td>{{ number_format($results->sum('clicks')) }}</td>
                                        <td>{{ number_format($results->sum('reach')) }}</td>
                                        <td>{{ number_format($results->sum('conversions')) }}</td>
                                        <td>${{ number_format($results->sum('spend'), 2) }}</td>
                                        <td>{{ number_format($results->avg('ctr'), 2) }}%</td>
                                        <td>${{ number_format($results->avg('cpc'), 4) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <p class="text-muted">{{ __('No analytics data available for the selected period') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($groupedResults->isNotEmpty())
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td>{{ __('Total') }}</td>
                                        <td>{{ number_format($campaignResults->sum('impressions')) }}</td>
                                        <td>{{ number_format($campaignResults->sum('clicks')) }}</td>
                                        <td>{{ number_format($campaignResults->sum('reach')) }}</td>
                                        <td>{{ number_format($campaignResults->sum('conversions')) }}</td>
                                        <td>${{ number_format($campaignResults->sum('spend'), 2) }}</td>
                                        <td>{{ number_format($campaignResults->avg('ctr'), 2) }}%</td>
                                        <td>${{ number_format($campaignResults->avg('cpc'), 4) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Account Insights') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Followers') }}</th>
                                    <th>{{ __('Reach') }}</th>
                                    <th>{{ __('Impressions') }}</th>
                                    <th>{{ __('Video Views') }}</th>
                                    <th>{{ __('Page Views') }}</th>
                                    <th>{{ __('Engagement') }}</th>
                                    <th>{{ __('Eng. Rate') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($insights->sortByDesc('date') as $insight)
                                    <tr>
                                        <td>{{ $insight->date->format('M d, Y') }}</td>
                                        <td>
                                            <i class="{{ $insight->account->platform_icon }}" style="color: {{ $insight->account->platform_color }};"></i>
                                            {{ $insight->account->account_name }}
                                        </td>
                                        <td>{{ number_format($insight->followers) }}</td>
                                        <td>{{ number_format($insight->metadata['reach'] ?? 0) }}</td>
                                        <td>{{ number_format($insight->metadata['impressions'] ?? 0) }}</td>
                                        <td>{{ number_format($insight->metadata['video_views'] ?? 0) }}</td>
                                        <td>{{ number_format($insight->page_views) }}</td>
                                        <td>{{ number_format($insight->total_engagement) }}</td>
                                        <td>{{ number_format($insight->engagement_rate, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <p class="text-muted">{{ __('No insights data available. Click the') }} <i class="ti ti-refresh"></i> {{ __('button on any account card to sync.') }}</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            // Weekly targets are pro-rated to the selected window: target × (days / 7).
            const PERIOD_DAYS = {{ (int) $periodDays }};
            const IS_REALTIME = {{ $isRealtime ? 'true' : 'false' }};
            const PRORATE     = PERIOD_DAYS / 7;

            const fmt = (n) => {
                n = Number(n) || 0;
                if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                if (n >= 1000)    return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
                return n.toLocaleString();
            };

            function loadCard(card) {
                const url    = card.dataset.statsUrl;
                const status = card.querySelector('.stat-status');

                status.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> {{ __('Loading…') }}';
                // reset every tile to the loading dash + clear target colours
                card.querySelectorAll('[data-stat]').forEach(el => {
                    el.textContent = '—';
                    el.classList.remove('text-muted');
                });
                card.querySelectorAll('.stat-tile').forEach(t => {
                    t.classList.remove('tile-up', 'tile-down');
                    const tl = t.querySelector('.target-line');
                    if (tl) tl.remove();
                });

                return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
                    .then(data => {
                        const s = data.stats || {};
                        // Fill every tile that exists in this card from the returned stats.
                        card.querySelectorAll('[data-stat]').forEach(el => {
                            const key  = el.dataset.stat;
                            const val  = s[key];
                            const tile = el.closest('.stat-tile');

                            if (val === null || val === undefined) {
                                el.textContent = 'N/A';
                                el.classList.add('text-muted');
                                return;
                            }
                            if (key === 'engagement_rate') {
                                el.textContent = (Number(val) || 0).toFixed(2) + '%';
                                return;
                            }
                            el.textContent = fmt(val);

                            // Target colouring (skipped in real-time, where numbers are mixed
                            // live-totals vs 30-day figures and a weekly target makes no sense).
                            if (!IS_REALTIME && tile) {
                                const weekly = parseFloat(tile.dataset.target || '0');
                                const invert = tile.dataset.invert === '1';
                                if (weekly > 0) {
                                    const goal = weekly * PRORATE;
                                    const ok   = invert ? (Number(val) <= goal) : (Number(val) >= goal);
                                    tile.classList.add(ok ? 'tile-up' : 'tile-down');
                                    const line = document.createElement('div');
                                    line.className = 'target-line text-truncate';
                                    line.textContent = '{{ __('Target') }}: ' + fmt(Math.round(goal));
                                    el.parentNode.appendChild(line);
                                }
                            }
                        });
                        let extra = (s.posts_count !== undefined) ? ' · ' + s.posts_count + ' {{ __('posts') }}' : '';
                        status.innerHTML = '<span class="text-success"><i class="ti ti-broadcast"></i> {{ __('Live') }}</span>' + extra;
                    })
                    .catch(err => {
                        card.querySelectorAll('[data-stat]').forEach(el => { el.textContent = '—'; });
                        status.innerHTML = '<span class="text-danger" title="' + (err && err.error ? err.error : '') + '"><i class="ti ti-alert-triangle"></i> {{ __('Token expired — reconnect') }}</span>';
                    });
            }

            // Load cards in small batches instead of firing all ~18 requests at once.
            // Hitting the Graph API with everything simultaneously triggers rate-limiting,
            // which returned intermittent zeros for whichever accounts got throttled.
            const cards = Array.from(document.querySelectorAll('.account-stat-card'));
            const CONCURRENCY = 3;
            let next = 0;
            function pump() {
                if (next >= cards.length) return;
                const card = cards[next++];
                Promise.resolve(loadCard(card)).finally(pump);
            }
            for (let i = 0; i < Math.min(CONCURRENCY, cards.length); i++) pump();

            document.querySelectorAll('.stat-reload').forEach(btn => {
                btn.addEventListener('click', function () {
                    loadCard(this.closest('.account-stat-card'));
                });
            });
        })();
    </script>
    @endpush
@endsection
