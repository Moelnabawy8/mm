@include('crm_reports.employee._head', [
    'title' => __('Lead stages'),
    'definition' => __('Where the leads created in the period stand now: by the status of their deals, and by how far each customer has gone (lead → sales meeting → deal → purchase → installments). In the journey each lead is counted once, at the furthest point it reached.'),
])

@php
    $journeyLabels = [
        'new' => __('No action'), 'meeting' => __('Meeting'), 'deal' => __('Deal'),
        'purchase' => __('Purchase'), 'installment' => __('On installments'),
    ];
    // Earliest stage first so the bars read left to right along the journey.
    $journeyOrder = array_reverse(\App\Services\CrmReports\EmployeeReport::JOURNEY);

    // More than 8 statuses cannot keep distinct colours: keep the first 7, fold the rest into a grey "Other".
    // Leads without a deal stay out of the chart (they are most leads and would hide the rest); the table has them.
    $statusName = fn ($s) => $s !== '' ? $s : __('No status');
    $shown = array_slice($statuses, 0, 7);
    $folded = array_slice($statuses, 7);
    $statusSeries = array_map(fn ($s, $i) => [
        'name' => $statusName($s), 'color' => 'cat' . ($i + 1),
        'data' => array_values(array_map(fn ($m) => $m['statuses'][$s] ?? 0, $matrix)),
    ], $shown, array_keys($shown));
    if ($folded) {
        $statusSeries[] = ['name' => __('Other statuses'), 'color' => 'status.neutral', 'data' => array_values(array_map(
            fn ($m) => array_sum(array_map(fn ($s) => $m['statuses'][$s] ?? 0, $folded)), $matrix))];
    }
    $statusSpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true,
        'categories' => array_values(array_column($matrix, 'name')), 'series' => $statusSeries];

    // One blue ramp, darker = further along; "no action" stays neutral grey.
    $chartSteps = [
        ['label' => __('No action'), 'keys' => ['new'], 'color' => 'status.neutral'],
        ['label' => __('Meeting'), 'keys' => ['meeting'], 'color' => 'ramp.3'],
        ['label' => __('Deal'), 'keys' => ['deal'], 'color' => 'ramp.2'],
        ['label' => __('Purchase'), 'keys' => ['purchase'], 'color' => 'ramp.1'],
        ['label' => __('On installments'), 'keys' => ['installment'], 'color' => 'ramp.0'],
    ];
    $journeySpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true, 'percent' => true,
        'categories' => array_values(array_column($journey, 'name')),
        'series' => array_map(fn ($step) => [
            'name' => $step['label'], 'color' => $step['color'],
            'data' => array_values(array_map(fn ($j) => array_sum(array_map(fn ($k) => $j['stages'][$k], $step['keys'])), $journey)),
        ], $chartSteps)];
@endphp

<div class="row g-3">
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header border-0 pb-0">
                <h6 class="crm-card-title">{{ __('Deal status per employee') }}</h6>
                <div class="crm-card-sub">{{ __('Leads by the status of their deals. A lead with several deals counts under each of their statuses.') }}</div>
            </div>
            <div class="card-body pt-2">
                @if (empty($matrix))
                    <div class="crm-empty"><i class="ti ti-layout-kanban"></i>{{ __('No leads were created in this period.') }}</div>
                @else
                    @if ($statuses)
                        <div class="crm-chart" data-spec="{{ json_encode($statusSpec) }}"></div>
                    @endif
                    <div class="table-responsive crm-table-wrap mt-2">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                @foreach ($statuses as $s)
                                    <th class="num" data-sort="num">{{ $statusName($s) }}</th>
                                @endforeach
                                <th class="num" data-sort="num">{{ __('No deal') }}</th>
                                <th class="num" data-sort="num">{{ __('Leads') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($matrix as $m)
                                <tr>
                                    <td>{{ $m['name'] }}</td>
                                    @foreach (array_merge(array_map(fn ($s) => $m['statuses'][$s] ?? 0, $statuses), [$m['none']]) as $n)
                                        <td class="num" data-v="{{ $n }}">
                                            {{ number_format($n) }}
                                            @if ($n && $m['total'])<span class="text-muted small">({{ round($n * 100 / $m['total']) }}%)</span>@endif
                                        </td>
                                    @endforeach
                                    <td class="num fw-bold" data-v="{{ $m['total'] }}">{{ number_format($m['total']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header border-0 pb-0">
                <h6 class="crm-card-title">{{ __('Customer journey per employee') }}</h6>
                <div class="crm-card-sub">{{ __('Share of each employee\'s leads by the furthest step reached') }}</div>
            </div>
            <div class="card-body pt-2">
                @if (empty($journey))
                    <div class="crm-empty"><i class="ti ti-route"></i>{{ __('No leads were created in this period.') }}</div>
                @else
                    <div class="crm-chart" data-spec="{{ json_encode($journeySpec) }}"></div>
                    <div class="table-responsive crm-table-wrap mt-2">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                @foreach ($journeyOrder as $k)
                                    <th class="num" data-sort="num">{{ $journeyLabels[$k] }}</th>
                                @endforeach
                                <th class="num" data-sort="num">{{ __('Total') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($journey as $j)
                                <tr>
                                    <td>{{ $j['name'] }}</td>
                                    @foreach ($journeyOrder as $k)
                                        <td class="num" data-v="{{ $j['stages'][$k] }}">{{ number_format($j['stages'][$k]) }}</td>
                                    @endforeach
                                    <td class="num fw-bold" data-v="{{ $j['total'] }}">{{ number_format($j['total']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
