@include('crm_reports.employee._head', [
    'title' => __('Meetings'),
    'definition' => __('Sales meetings dated inside the period. A meeting counts for the employee recorded on it; older meetings, from before that was recorded, count for the employees the lead is assigned to. Meetings with no recorded status show as past or upcoming by their date.'),
])

@php
    $statusLabels = [
        'scheduled' => __('Scheduled'), 'done' => __('Done'), 'no_show' => __('No show'), 'cancelled' => __('Cancelled'),
        'past' => __('Past (no status)'), 'upcoming' => __('Upcoming (no status)'),
    ];
    $typeNames = array_map(fn ($t) => $t !== '' ? $t : __('No type'), $types);

    // Types beyond 7 fold into Other so every colour stays distinct.
    $shownTypes = array_slice($types, 0, 7);
    $foldTypes = array_slice($types, 7);
    $typeSeries = [];
    foreach ($shownTypes as $i => $t) {
        $typeSeries[] = ['name' => $t !== '' ? $t : __('No type'), 'color' => 'cat' . ($i + 1),
            'data' => $perEmployee->map(fn ($r) => $typeMatrix[(int) $r->uid][$t] ?? 0)->values()->all()];
    }
    if ($foldTypes) {
        $typeSeries[] = ['name' => __('Other'), 'color' => 'cat8',
            'data' => $perEmployee->map(fn ($r) => array_sum(array_map(fn ($t) => $typeMatrix[(int) $r->uid][$t] ?? 0, $foldTypes)))->values()->all()];
    }
    $typeSpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'categories' => $perEmployee->pluck('name')->all(), 'series' => $typeSeries];

    $statusRows = $byStatus->sortByDesc('n')->values();
    $statusSpec = ['type' => 'bar', 'horizontal' => true, 'labels' => true, 'distributed' => false,
        'categories' => $statusRows->map(fn ($r) => $statusLabels[$r->status] ?? $r->status)->all(),
        'series' => [['name' => __('Meetings'), 'data' => $statusRows->pluck('n')->map(fn ($n) => (int) $n)->all(), 'color' => 'cat1']]];
    $countrySpec = ['type' => 'bar', 'horizontal' => true, 'labels' => true,
        'categories' => $byCountry->map(fn ($r) => $r->country !== '' ? __($r->country) : __('No country'))->all(),
        'series' => [['name' => __('Meetings'), 'data' => $byCountry->pluck('n')->map(fn ($n) => (int) $n)->all(), 'color' => 'cat1']]];
    $trendSpec = ['type' => 'area', 'categories' => $trend['categories'],
        'series' => [['name' => __('Meetings'), 'data' => $trend['data'], 'color' => 'cat1']]];
@endphp

<form data-section-filter class="crm-no-print mb-3 row g-2">
    <div class="col-md-3 col-sm-4">
        <select name="meeting_type" class="form-select form-select-sm" aria-label="{{ __('Meeting type') }}">
            <option value="">{{ __('All meeting types') }}</option>
            @foreach ($meetingTypes as $id => $name)
                <option value="{{ $id }}" @selected($meetingFilters['meeting_type'] === (int) $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 col-sm-4">
        <select name="meeting_status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statusLabels as $k => $label)
                <option value="{{ $k }}" @selected($meetingFilters['meeting_status'] === $k)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 col-sm-4">
        <select name="meeting_country" class="form-select form-select-sm" aria-label="{{ __('Country') }}">
            <option value="">{{ __('All countries') }}</option>
            @foreach (\App\Services\LeadScope::COUNTRIES as $c)
                <option value="{{ $c }}" @selected($meetingFilters['meeting_country'] === $c)>{{ __($c) }}</option>
            @endforeach
        </select>
    </div>
</form>

@if ($perEmployee->isEmpty())
    <div class="crm-empty"><i class="ti ti-calendar-off"></i>{{ __('No meetings for these filters.') }}</div>
@else
    @php
        $tDone = $perEmployee->sum('done'); $tNoShow = $perEmployee->sum('no_show');
        $tDeal = $perEmployee->sum('to_deal');
    @endphp
    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => __('Meetings'), 'v' => number_format($total), 'icon' => 'ti-calendar-event'],
            ['label' => __('Done'), 'v' => number_format($tDone), 'icon' => 'ti-circle-check'],
            ['label' => __('No show'), 'v' => number_format($tNoShow), 'icon' => 'ti-user-x'],
            ['label' => __('Meeting to deal'), 'v' => ($total ? round($tDeal * 100 / $total, 1) : 0) . '%', 'icon' => 'ti-briefcase'],
        ] as $tile)
            <div class="col-md-3 col-6">
                <div class="card crm-kpi mb-0"><div class="card-body">
                    <div class="crm-kpi-label"><i class="ti {{ $tile['icon'] }}"></i>{{ $tile['label'] }}</div>
                    <div class="crm-kpi-value">{{ $tile['v'] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Meetings per employee by type') }}</h6></div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($typeSpec) }}"></div></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('By status') }}</h6></div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($statusSpec) }}"></div></div>
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
                                <th class="num" data-sort="num">{{ __('Meetings') }}</th>
                                <th class="num" data-sort="num">{{ __('Leads met') }}</th>
                                <th class="num" data-sort="num">{{ __('Done') }}</th>
                                <th class="num" data-sort="num">{{ __('No show') }}</th>
                                <th class="num" data-sort="num">{{ __('Cancelled') }}</th>
                                <th class="num" data-sort="num">{{ __('Led to deal') }}</th>
                                <th class="num" data-sort="num">{{ __('Meeting to deal') }}</th>
                                @foreach ($typeNames as $tn)
                                    <th class="num" data-sort="num">{{ $tn }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($perEmployee as $r)
                                <tr>
                                    <td>{{ $r->name }}</td>
                                    <td class="num" data-v="{{ $r->n }}">{{ number_format($r->n) }}</td>
                                    <td class="num" data-v="{{ $r->leads }}">{{ number_format($r->leads) }}</td>
                                    <td class="num" data-v="{{ $r->done }}">{{ number_format($r->done) }}</td>
                                    <td class="num" data-v="{{ $r->no_show }}">{{ number_format($r->no_show) }}</td>
                                    <td class="num" data-v="{{ $r->cancelled }}">{{ number_format($r->cancelled) }}</td>
                                    <td class="num" data-v="{{ $r->to_deal }}">{{ number_format($r->to_deal) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => $r->deal_rate])
                                    @foreach ($types as $t)
                                        @php $n = $typeMatrix[(int) $r->uid][$t] ?? 0; @endphp
                                        <td class="num" data-v="{{ $n }}">{{ number_format($n) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Meetings over time') }}</h6></div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($trendSpec) }}"></div></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('By country') }}</h6>
                    <div class="crm-card-sub">{{ __('The customer\'s country on the lead') }}</div>
                </div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($countrySpec) }}"></div></div>
            </div>
        </div>
    </div>
@endif
