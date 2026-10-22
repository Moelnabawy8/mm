@include('crm_reports.employee._head', [
    'title' => __('Neglected leads'),
    'definition' => __('Leads created in the period that nobody is working: never had an action (a sales meeting or a deal), or their last action is older than the chosen number of days. Leads that already purchased, or whose latest deal is Won or Lost, are left out. Idle days = days since the last sales meeting or deal, or since the lead arrived if it never had one.'),
])

@php
    $bucketLabels = ['0_2' => __('0–2 days'), '3_7' => __('3–7 days'), '8_14' => __('8–14 days'), '15_30' => __('15–30 days'), '30_plus' => __('Over 30 days')];
    $bucketColors = ['0_2' => 'status.good', '3_7' => 'ramp.3', '8_14' => 'status.warning', '15_30' => 'status.serious', '30_plus' => 'status.critical'];
    $neglectSpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true,
        'categories' => $rows->pluck('name')->all(),
        'series' => [
            ['name' => __('Never actioned'), 'data' => $rows->pluck('never')->all(), 'color' => 'status.critical'],
            ['name' => __('Stopped (no action for :d+ days)', ['d' => $staleDays]), 'data' => $rows->pluck('stale')->all(), 'color' => 'status.warning'],
        ]];
    $ageSpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true,
        'categories' => $rows->pluck('name')->all(),
        'series' => collect($bucketLabels)->map(fn ($label, $k) => [
            'name' => $label, 'color' => $bucketColors[$k], 'data' => $rows->pluck('b_' . $k)->all(),
        ])->values()->all()];
    $srcSpec = ['type' => 'bar', 'horizontal' => true, 'labels' => true, 'categories' => $bySource->pluck('label')->all(),
        'series' => [['name' => __('Neglected leads'), 'data' => $bySource->pluck('n')->all(), 'color' => 'cat1']]];
    $cmpSpec = ['type' => 'bar', 'horizontal' => true, 'labels' => true, 'categories' => $byCampaign->pluck('label')->all(),
        'series' => [['name' => __('Neglected leads'), 'data' => $byCampaign->pluck('n')->all(), 'color' => 'cat1']]];
@endphp

<form data-section-filter class="crm-no-print mb-3 d-flex align-items-center gap-2 flex-wrap">
    <label class="small text-muted mb-0" for="crm-stale">{{ __('Count a lead as stopped after') }}</label>
    <select name="stale" id="crm-stale" class="form-select form-select-sm" style="width:auto">
        @foreach ($staleOptions as $d)
            <option value="{{ $d }}" @selected($d === $staleDays)>{{ $d }} {{ __('days without action') }}</option>
        @endforeach
    </select>
</form>

@if ($rows->isEmpty())
    <div class="crm-empty"><i class="ti ti-mood-happy"></i>{{ __('No open leads for these filters.') }}</div>
@else
    @php $tOpen = $rows->sum('open_leads'); $tNever = $rows->sum('never'); $tStale = $rows->sum('stale'); @endphp
    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => __('Open leads'), 'v' => $tOpen, 'icon' => 'ti-folder'],
            ['label' => __('Never actioned'), 'v' => $tNever, 'icon' => 'ti-alert-octagon', 'pct' => $tOpen ? round($tNever * 100 / $tOpen, 1) : 0],
            ['label' => __('Stopped'), 'v' => $tStale, 'icon' => 'ti-player-pause', 'pct' => $tOpen ? round($tStale * 100 / $tOpen, 1) : 0],
            ['label' => __('Avg. idle days'), 'v' => $tOpen ? round($rows->sum(fn ($r) => $r->avg_idle * $r->open_leads) / $tOpen, 1) : 0, 'icon' => 'ti-hourglass'],
        ] as $tile)
            <div class="col-md-3 col-6">
                <div class="card crm-kpi mb-0"><div class="card-body">
                    <div class="crm-kpi-label"><i class="ti {{ $tile['icon'] }}"></i>{{ $tile['label'] }}</div>
                    <div class="crm-kpi-value">{{ is_float($tile['v']) ? $tile['v'] : number_format($tile['v']) }}</div>
                    @isset($tile['pct'])<div class="crm-kpi-delta flat">{{ $tile['pct'] }}% {{ __('of open leads') }}</div>@endisset
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Neglected leads per employee') }}</h6></div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($neglectSpec) }}"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('How long open leads have been idle') }}</h6>
                    <div class="crm-card-sub">{{ __('Days since the last action (or since arrival)') }}</div>
                </div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($ageSpec) }}"></div></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="table-responsive crm-table-wrap">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                <th class="num" data-sort="num">{{ __('Open leads') }}</th>
                                <th class="num" data-sort="num">{{ __('Never actioned') }}</th>
                                <th class="num" data-sort="num">{{ __('Stopped') }}</th>
                                <th class="num" data-sort="num">{{ __('Neglect rate') }}</th>
                                @foreach ($bucketLabels as $label)
                                    <th class="num" data-sort="num">{{ $label }}</th>
                                @endforeach
                                <th class="num" data-sort="num">{{ __('Avg. idle days') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>{{ $r->name }}</td>
                                    <td class="num" data-v="{{ $r->open_leads }}">{{ number_format($r->open_leads) }}</td>
                                    <td class="num" data-v="{{ $r->never }}">{{ number_format($r->never) }}</td>
                                    <td class="num" data-v="{{ $r->stale }}">{{ number_format($r->stale) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => $r->neglect_rate])
                                    @foreach (array_keys($bucketLabels) as $k)
                                        <td class="num" data-v="{{ $r->{'b_' . $k} }}">{{ number_format($r->{'b_' . $k}) }}</td>
                                    @endforeach
                                    <td class="num" data-v="{{ $r->avg_idle }}">{{ $r->avg_idle }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Neglected leads by source') }}</h6></div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($srcSpec) }}"></div></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Neglected leads by campaign') }}</h6></div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($cmpSpec) }}"></div></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('The leads to follow up first') }}</h6>
                    <div class="crm-card-sub">{{ __('Most idle first') }} · {{ number_format($total) }} {{ __('leads') }} · {{ __('Excel export has the full list') }}</div>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th>{{ __('Lead #') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Phone') }}</th>
                                <th>{{ __('Assigned to') }}</th>
                                <th>{{ __('Deal status') }}</th>
                                <th>{{ __('Campaign') }}</th>
                                <th>{{ __('Created') }}</th>
                                <th>{{ __('Last action') }}</th>
                                <th class="num">{{ __('Idle days') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($list as $l)
                                <tr>
                                    <td><a href="{{ route('leads.show', $l->id) }}" target="_blank" rel="noopener">#{{ $l->id }}</a></td>
                                    <td>{{ $l->name }}</td>
                                    <td dir="ltr">{{ $l->phone }}</td>
                                    <td>{{ $l->owner }}</td>
                                    <td>{{ $l->deal_status ?: __('No deal') }}</td>
                                    <td>{{ $l->campaign_name ?: '–' }}</td>
                                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('Y-m-d') }}</td>
                                    <td class="text-nowrap">
                                        @if ($l->last_at)
                                            {{ \Carbon\Carbon::parse($l->last_at)->format('Y-m-d') }}
                                        @else
                                            <span class="badge bg-light-danger text-danger">{{ __('Never') }}</span>
                                        @endif
                                    </td>
                                    <td class="num fw-bold">{{ number_format($l->idle_days) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 crm-no-print">{{ $list->appends(request()->except('page'))->links('pagination::bootstrap-5') }}</div>
                </div>
            </div>
        </div>
    </div>
@endif
