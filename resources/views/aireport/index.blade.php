@extends('layouts.admin')

@section('page-title'){{ __('AI Financial Reports') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item active">{{ __('AI Reports') }}</li>
@endsection

@section('content')

{{-- AI Insights --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="bg-primary text-white rounded p-1"><i class="ti ti-robot" style="font-size:18px"></i></span>
                <h5 class="mb-0">{{ __('AI Insights') }}</h5>
                <span class="badge bg-primary ms-1">{{ __('Powered by your data') }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($insights as $insight)
                    <div class="col-md-6">
                        <div class="d-flex gap-3 p-3 rounded-2 bg-light-{{ $insight['type'] }} border border-{{ $insight['type'] }}">
                            <span class="text-{{ $insight['type'] }} mt-1">
                                <i class="ti {{ $insight['icon'] }}" style="font-size:22px"></i>
                            </span>
                            <p class="mb-0 text-{{ $insight['type'] }} fw-medium">{{ $insight['text'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    @php
    $kpis = [
        ['label' => __('Total Income'),    'value' => number_format($totalIncome, 2),   'icon' => 'ti-arrow-up-circle',   'color' => 'success'],
        ['label' => __('Total Expenses'),  'value' => number_format($totalExpense, 2),  'icon' => 'ti-arrow-down-circle', 'color' => 'danger'],
        ['label' => __('Net Profit'),      'value' => number_format($netProfit, 2),     'icon' => 'ti-trending-up',       'color' => $netProfit >= 0 ? 'primary' : 'warning'],
        ['label' => __('Invoices'),        'value' => $totalInvoices,                   'icon' => 'ti-file-invoice',      'color' => 'info'],
        ['label' => __('Bills'),           'value' => $totalBills,                      'icon' => 'ti-receipt',           'color' => 'warning'],
        ['label' => __('Employees'),       'value' => $totalEmployees,                  'icon' => 'ti-users',             'color' => 'secondary'],
    ];
    @endphp
    @foreach($kpis as $kpi)
    <div class="col-sm-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <span class="bg-light-{{ $kpi['color'] }} text-{{ $kpi['color'] }} rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                      style="width:44px;height:44px">
                    <i class="ti {{ $kpi['icon'] }}" style="font-size:20px"></i>
                </span>
                <h4 class="fw-bold mb-0 text-{{ $kpi['color'] }}">{{ $kpi['value'] }}</h4>
                <div class="text-muted" style="font-size:12px">{{ $kpi['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Charts row --}}
<div class="row g-4 mb-4">
    {{-- Income vs Expense Chart --}}
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Income vs Expenses') }} <small class="text-muted">({{ __('Last 6 Months') }})</small></h5>
            </div>
            <div class="card-body">
                <div id="incomeExpenseChart" style="min-height:280px"></div>
            </div>
        </div>
    </div>
    {{-- Invoice Status --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Invoice Status') }}</h5>
            </div>
            <div class="card-body">
                @if(array_sum($invoiceStats) > 0)
                    <div id="invoiceStatusChart" style="min-height:280px"></div>
                @else
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-file-off" style="font-size:40px;color:#ccc"></i>
                        <p class="mt-2">{{ __('No invoice data') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- This month highlight --}}
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">{{ __('This Month') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted"><i class="ti ti-arrow-up text-success me-1"></i>{{ __('Income') }}</span>
                    <span class="fw-bold text-success">{{ number_format($thisMonthIncome, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted"><i class="ti ti-arrow-down text-danger me-1"></i>{{ __('Expenses') }}</span>
                    <span class="fw-bold text-danger">{{ number_format($thisMonthExpense, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted"><i class="ti ti-calculator text-primary me-1"></i>{{ __('Net') }}</span>
                    @php $monthNet = $thisMonthIncome - $thisMonthExpense; @endphp
                    <span class="fw-bold text-{{ $monthNet >= 0 ? 'primary' : 'danger' }}">
                        {{ number_format($monthNet, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 d-flex align-items-center justify-content-center">
            <div class="card-body text-center">
                <i class="ti ti-brain text-primary" style="font-size:48px;opacity:.4"></i>
                <h6 class="mt-3 text-muted">{{ __('More AI features coming soon') }}</h6>
                <p class="text-muted small">{{ __('Forecasting, anomaly detection, and automated reports will be available in the next update.') }}</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/plugins/apexcharts.min.js') }}"></script>
<script>
// Income vs Expense
new ApexCharts(document.getElementById('incomeExpenseChart'), {
    chart: { type: 'area', height: 280, toolbar: { show: false }, sparkline: { enabled: false } },
    series: [
        { name: '{{ __('Income') }}',   data: @json($incomeByMonth) },
        { name: '{{ __('Expenses') }}', data: @json($expenseByMonth) }
    ],
    xaxis: { categories: @json($months) },
    colors: ['#2dce89', '#f5365c'],
    fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: .05 } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    legend: { position: 'top' },
    grid: { borderColor: '#f1f1f1' },
    tooltip: { y: { formatter: val => val.toLocaleString() } }
}).render();

@if(array_sum($invoiceStats) > 0)
new ApexCharts(document.getElementById('invoiceStatusChart'), {
    chart: { type: 'donut', height: 280 },
    series: @json(array_values($invoiceStats)),
    labels: @json(array_keys($invoiceStats)),
    colors: ['#11cdef','#2dce89','#fb6340','#f5365c','#5e72e4'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true },
    plotOptions: { pie: { donut: { size: '60%' } } }
}).render();
@endif
</script>
@endpush
