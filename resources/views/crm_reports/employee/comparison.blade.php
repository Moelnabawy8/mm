@include('crm_reports.employee._head', [
    'title' => __('Quick comparison'),
    'definition' => __('Employees side by side on the leads created in the period. Call meeting rate and presentation rate = share of the leads that had a sales meeting of that type. Rates are only ranked for employees with at least 5 leads, so one lucky lead does not top the board.'),
])

@if ($rows->isEmpty())
    <div class="crm-empty"><i class="ti ti-trophy"></i>{{ __('No leads were created in this period.') }}</div>
@else
    @php
        $names = $rows->pluck('name')->all();
        $multiples = [
            ['title' => __('Leads received'), 'key' => 'leads', 'suffix' => '', 'avg' => null],
            ['title' => __('Call meeting rate'), 'key' => 'call_rate', 'suffix' => '%', 'avg' => $teamAvg['call_rate']],
            ['title' => __('Presentation rate'), 'key' => 'presentation_rate', 'suffix' => '%', 'avg' => $teamAvg['presentation_rate']],
            ['title' => __('Conversion rate'), 'key' => 'conversion', 'suffix' => '%', 'avg' => $teamAvg['conversion']],
        ];
    @endphp

    <div class="row g-3 mb-3">
        @foreach ($boards as $b)
            <div class="col-xl-3 col-md-6">
                <div class="card h-100 mb-0">
                    <div class="card-header border-0 pb-0"><h6 class="crm-card-title"><i class="ti ti-trophy text-warning"></i> {{ $b['label'] }}</h6></div>
                    <div class="card-body pt-2">
                        @forelse ($b['rows'] as $i => $r)
                            <div class="crm-board-row">
                                <span class="crm-rank">{{ $i + 1 }}</span>
                                <span class="crm-board-name" title="{{ $r->name }}">{{ $r->name }}</span>
                                <span class="crm-board-val">{{ $b['suffix'] === '%' ? number_format($r->{$b['key']}, 1) : number_format($r->{$b['key']}) }}{{ $b['suffix'] }}</span>
                            </div>
                        @empty
                            <div class="crm-card-sub">{{ __('Not enough data') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        @foreach ($multiples as $m)
            @php
                $spec = ['type' => 'bar', 'horizontal' => true, 'labels' => true, 'suffix' => $m['suffix'],
                    'categories' => $names, 'series' => [['name' => $m['title'], 'data' => $rows->pluck($m['key'])->all(), 'color' => 'cat1']]];
                if ($m['avg'] !== null) { $spec['avg'] = $m['avg']; $spec['avgLabel'] = __('Team'); }
            @endphp
            <div class="col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header border-0 pb-0">
                        <h6 class="crm-card-title">{{ $m['title'] }}</h6>
                        @if ($m['avg'] !== null)<div class="crm-card-sub">{{ __('Team average') }}: {{ $m['avg'] }}%</div>@endif
                    </div>
                    <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($spec) }}"></div></div>
                </div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="table-responsive crm-table-wrap">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                <th class="num" data-sort="num">{{ __('Leads') }}</th>
                                <th class="num" data-sort="num">{{ __('Action rate') }}</th>
                                <th class="num" data-sort="num">{{ __('Call meeting rate') }}</th>
                                <th class="num" data-sort="num">{{ __('Presentation rate') }}</th>
                                <th class="num" data-sort="num">{{ __('Converted to deal') }}</th>
                                <th class="num" data-sort="num">{{ __('Conversion rate') }}</th>
                                <th class="num" data-sort="num">{{ __('vs team') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $r)
                                @php $vs = round($r->conversion - $teamAvg['conversion'], 1); @endphp
                                <tr>
                                    <td>{{ $r->name }}</td>
                                    <td class="num" data-v="{{ $r->leads }}">{{ number_format($r->leads) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => $r->action_rate])
                                    @include('crm_reports.employee._rate', ['v' => $r->call_rate])
                                    @include('crm_reports.employee._rate', ['v' => $r->presentation_rate])
                                    <td class="num" data-v="{{ $r->dealt }}">{{ number_format($r->dealt) }}</td>
                                    @include('crm_reports.employee._rate', ['v' => $r->conversion])
                                    <td class="num" data-v="{{ $vs }}">
                                        <span class="crm-delta {{ $vs > 0 ? 'up' : ($vs < 0 ? 'down' : '') }}">{{ $vs > 0 ? '+' : '' }}{{ $vs }} {{ __('pts') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="crm-card-sub mt-2">{{ __('vs team = conversion rate minus the team\'s, in percentage points.') }}</div>
                </div>
            </div>
        </div>
    </div>
@endif
