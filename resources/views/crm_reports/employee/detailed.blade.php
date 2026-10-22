@include('crm_reports.employee._head', [
    'title' => __('Detailed comparison'),
    'definition' => __('Everything per employee for the leads created in the period: sales meetings by type, deals and how the customers paid. A customer is counted once per payment type even with several purchases. Revenue is the purchase price.'),
])

@if ($rows->isEmpty())
    <div class="crm-empty"><i class="ti ti-table"></i>{{ __('No leads were created in this period.') }}</div>
@else
    @php
        $deltaCell = function ($now, $before) {
            if (!$before) { return $now ? '<span class="badge bg-light text-dark">' . e(__('New')) . '</span>' : ''; }
            $d = round(($now - $before) * 100 / $before, 1);
            return '<span class="crm-delta ' . ($d > 0 ? 'up' : ($d < 0 ? 'down' : '')) . '">' . ($d > 0 ? '+' : '') . $d . '%</span>';
        };
        $payRows = $rows->filter(fn ($r) => $r->cash + $r->installment > 0)->values();
        $paySpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'categories' => $payRows->pluck('name')->all(),
            'series' => [
                ['name' => __('Cash customers'), 'data' => $payRows->pluck('cash')->all(), 'color' => 'cat1'],
                ['name' => __('Installment customers'), 'data' => $payRows->pluck('installment')->all(), 'color' => 'cat2'],
            ]];
        $revRows = $rows->sortByDesc('revenue')->filter(fn ($r) => $r->revenue > 0)->values();
        $revSpec = ['type' => 'bar', 'horizontal' => true, 'labels' => true, 'categories' => $revRows->pluck('name')->all(),
            'series' => [['name' => __('Revenue'), 'data' => $revRows->pluck('revenue')->all(), 'color' => 'cat1']]];
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <div class="table-responsive crm-table-wrap">
                <table class="table crm-table table-hover">
                    <thead>
                    <tr>
                        <th data-sort="text">{{ __('Employee') }}</th>
                        <th class="num" data-sort="num">{{ __('Leads') }}</th>
                        <th class="num" data-sort="num">{{ __('Leads with a call meeting') }}</th>
                        <th class="num" data-sort="num">{{ __('Call meeting rate') }}</th>
                        <th class="num" data-sort="num">{{ __('Leads with a presentation') }}</th>
                        <th class="num" data-sort="num">{{ __('Presentation rate') }}</th>
                        <th class="num" data-sort="num">{{ __('Leads with meeting') }}</th>
                        <th class="num" data-sort="num">{{ __('Meetings') }}</th>
                        <th class="num" data-sort="num">{{ __('Deals') }}</th>
                        <th class="num" data-sort="num">{{ __('Conversion rate') }}</th>
                        <th class="num" data-sort="num">{{ __('Cash customers') }}</th>
                        <th class="num" data-sort="num">{{ __('Installment customers') }}</th>
                        <th class="num" data-sort="num">{{ __('Revenue') }}</th>
                        <th class="num" data-sort="num">{{ __('Qualified') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>{{ $r->name }}</td>
                            <td class="num" data-v="{{ $r->leads }}">{{ number_format($r->leads) }} @if ($ctx->hasComparison()){!! $deltaCell($r->leads, $r->prev_leads) !!}@endif</td>
                            <td class="num" data-v="{{ $r->met_call }}">{{ number_format($r->met_call) }}</td>
                            @include('crm_reports.employee._rate', ['v' => $r->call_rate])
                            <td class="num" data-v="{{ $r->met_pres }}">{{ number_format($r->met_pres) }}</td>
                            @include('crm_reports.employee._rate', ['v' => $r->presentation_rate])
                            <td class="num" data-v="{{ $r->met }}">{{ number_format($r->met) }}</td>
                            <td class="num" data-v="{{ $r->meetings }}">{{ number_format($r->meetings) }}</td>
                            <td class="num" data-v="{{ $r->dealt }}">{{ number_format($r->dealt) }} @if ($ctx->hasComparison()){!! $deltaCell($r->dealt, $r->prev_dealt) !!}@endif</td>
                            @include('crm_reports.employee._rate', ['v' => $r->conversion])
                            <td class="num" data-v="{{ $r->cash }}">{{ number_format($r->cash) }}</td>
                            <td class="num" data-v="{{ $r->installment }}">{{ number_format($r->installment) }}</td>
                            <td class="num" data-v="{{ $r->revenue }}">{{ number_format($r->revenue, 0) }}</td>
                            <td class="num" data-v="{{ $r->qualified }}">{{ number_format($r->qualified) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>{{ __('Total') }}</td>
                        <td class="num">{{ number_format($totals->leads) }}</td>
                        <td class="num">{{ number_format($totals->met_call) }}</td>
                        <td class="num">{{ $totals->call_rate }}%</td>
                        <td class="num">{{ number_format($totals->met_pres) }}</td>
                        <td class="num">{{ $totals->presentation_rate }}%</td>
                        <td class="num">{{ number_format($totals->met) }}</td>
                        <td class="num">{{ number_format($totals->meetings) }}</td>
                        <td class="num">{{ number_format($totals->dealt) }}</td>
                        <td class="num">{{ $totals->conversion }}%</td>
                        <td class="num">{{ number_format($totals->cash) }}</td>
                        <td class="num">{{ number_format($totals->installment) }}</td>
                        <td class="num">{{ number_format($totals->revenue, 0) }}</td>
                        <td class="num">{{ number_format($totals->qualified) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
            <div class="crm-card-sub mt-2">
                {{ __('Call meeting / presentation = the lead had a sales meeting of that type. Qualified = marked as a potential customer in Quality Evaluation.') }}
                @if ($ctx->hasComparison()) {{ __('Percent changes compare with the previous period.') }} @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Cash vs installment customers') }}</h6></div>
                <div class="card-body pt-2">
                    @if ($payRows->isEmpty())
                        <div class="crm-empty"><i class="ti ti-cash-off"></i>{{ __('No purchases from these leads yet.') }}</div>
                    @else
                        <div class="crm-chart" data-spec="{{ json_encode($paySpec) }}"></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Revenue per employee') }}</h6></div>
                <div class="card-body pt-2">
                    @if ($revRows->isEmpty())
                        <div class="crm-empty"><i class="ti ti-cash-off"></i>{{ __('No purchases from these leads yet.') }}</div>
                    @else
                        <div class="crm-chart" data-spec="{{ json_encode($revSpec) }}"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
