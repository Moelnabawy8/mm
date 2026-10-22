@include('crm_reports.employee._head', [
    'title' => __('Leads per employee'),
    'definition' => __('Leads created in the period, credited to the employees they are assigned to. A lead assigned to two employees counts for both, so the rows can add up to more than the total. Previous period = the same number of days right before the chosen period.'),
])

@if ($rows->isEmpty())
    <div class="crm-empty"><i class="ti ti-users"></i>{{ __('No leads were created in this period.') }}</div>
@else
    @php
        $byEmployee = [
            'type' => 'bar', 'horizontal' => true, 'labels' => true,
            'categories' => $rows->pluck('name')->all(),
            'series' => [['name' => __('Leads'), 'data' => $rows->pluck('leads')->all(), 'color' => 'cat1']],
        ];
        $sourceSpec = [
            'type' => 'bar', 'horizontal' => true, 'labels' => true,
            'categories' => $source->pluck('source')->all(),
            'series' => [['name' => __('Leads'), 'data' => $source->pluck('n')->all(), 'color' => 'cat1']],
        ];
    @endphp

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('Leads per employee') }}</h6>
                    <div class="crm-card-sub">{{ __('Total') }}: {{ number_format($total) }}</div>
                </div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($byEmployee) }}"></div></div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('Details') }}</h6>
                    @if ($ctx->hasComparison())
                        <div class="crm-card-sub">{{ __('Previous period') }}: {{ $ctx->prevFrom->format('Y-m-d') }} → {{ $ctx->prevTo->format('Y-m-d') }}</div>
                    @endif
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive crm-table-wrap">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                <th class="num" data-sort="num">{{ __('Leads') }}</th>
                                @if ($ctx->hasComparison())
                                    <th class="num" data-sort="num">{{ __('Previous period') }}</th>
                                @endif
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>{{ $r->name }}</td>
                                    <td class="num" data-v="{{ $r->leads }}">{{ number_format($r->leads) }}</td>
                                    @if ($ctx->hasComparison())
                                        <td class="num" data-v="{{ $r->prev }}">{{ number_format($r->prev) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr>
                                <td>{{ __('Total') }}</td>
                                <td class="num">{{ number_format($total) }}</td>
                                @if ($ctx->hasComparison())
                                    <td class="num">{{ number_format($prevTotal) }}</td>
                                @endif
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('Leads by source') }}</h6>
                    <div class="crm-card-sub">{{ __('A lead with several sources counts under each') }}</div>
                </div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($sourceSpec) }}"></div></div>
            </div>
        </div>
    </div>
@endif
