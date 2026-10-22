@include('crm_reports.employee._head', [
    'title' => __('Actions & conversion'),
    'definition' => __('For leads created in the period: how many had an action (a sales meeting or a deal - a purchase or installments always come with a deal) and how many became a deal.'),
])

@if ($rows->isEmpty())
    <div class="crm-empty"><i class="ti ti-hand-click"></i>{{ __('No leads were created in this period.') }}</div>
@else
    @php
        $funnelTop = max(1, $funnel[0]['n']);
        $funnelSpec = [
            'type' => 'bar', 'horizontal' => true, 'labels' => true,
            'categories' => array_column($funnel, 'label'),
            'series' => [['name' => __('Leads'), 'data' => array_column($funnel, 'n'), 'color' => 'cat1']],
        ];
        $splitSpec = [
            'type' => 'bar', 'horizontal' => true, 'stacked' => true,
            'categories' => $rows->pluck('name')->all(),
            'series' => [
                ['name' => __('Converted to deal'), 'data' => $rows->pluck('dealt')->all(), 'color' => 'cat1'],
                ['name' => __('No deal'), 'data' => $rows->map(fn ($r) => $r->leads - $r->dealt)->all(), 'color' => 'status.neutral'],
            ],
        ];
    @endphp

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('Team funnel') }}</h6>
                    <div class="crm-card-sub">{{ __('Each step as a share of leads received') }}</div>
                </div>
                <div class="card-body pt-2">
                    <div class="crm-chart" data-spec="{{ json_encode($funnelSpec) }}"></div>
                    <div class="table-responsive">
                        <table class="table crm-table">
                            <tbody>
                            @foreach ($funnel as $step)
                                <tr>
                                    <td>{{ $step['label'] }}</td>
                                    <td class="num">{{ number_format($step['n']) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => round($step['n'] * 100 / $funnelTop, 1)])
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
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('What happened to each employee\'s leads') }}</h6>
                </div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($splitSpec) }}"></div></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Details') }}</h6></div>
                <div class="card-body pt-2">
                    <div class="table-responsive crm-table-wrap">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                <th class="num" data-sort="num">{{ __('Leads') }}</th>
                                <th class="num" data-sort="num">{{ __('Action taken') }}</th>
                                <th class="num" data-sort="num">{{ __('Action rate') }}</th>
                                <th class="num" data-sort="num">{{ __('Converted to deal') }}</th>
                                <th class="num" data-sort="num">{{ __('Conversion rate') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>{{ $r->name }}</td>
                                    <td class="num" data-v="{{ $r->leads }}">{{ number_format($r->leads) }}</td>
                                    <td class="num" data-v="{{ $r->actioned }}">{{ number_format($r->actioned) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => $r->action_rate])
                                    <td class="num" data-v="{{ $r->dealt }}">{{ number_format($r->dealt) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => $r->conversion])
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            @php
                                // Each lead once (the rows count a co-assigned lead for both employees).
                                $team = collect($funnel)->pluck('n', 'key');
                                $tl = $team['leads']; $ta = $team['actioned']; $td = $team['dealt'];
                            @endphp
                            <tr>
                                <td>{{ __('Total') }}</td>
                                <td class="num">{{ number_format($tl) }}</td>
                                <td class="num">{{ number_format($ta) }}</td>
                                <td class="num">{{ $tl ? round($ta * 100 / $tl, 1) : 0 }}%</td>
                                <td class="num">{{ number_format($td) }}</td>
                                <td class="num">{{ $tl ? round($td * 100 / $tl, 1) : 0 }}%</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
