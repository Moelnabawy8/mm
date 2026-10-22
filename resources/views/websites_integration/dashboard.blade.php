@extends('layouts.admin')

@section('page-title')
    {{ __('Websites Dashboard') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item">{{ __('Overview') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="alert alert-success d-flex align-items-center">
                <i class="ti ti-check me-2 fs-4"></i>
                <div>
                    <strong>{{ __('Live Data') }}</strong> · my-communication.com (Arabic)
                    <small class="text-muted ms-2">{{ __('Last sync') }}: {{ \Carbon\Carbon::parse($kpis['fetched_at'])->diffForHumans() }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @php
            $cards = [
                ['label' => __('Customers'),       'value' => number_format($kpis['users']),               'icon' => 'users',         'color' => 'primary'],
                ['label' => __('Total Orders'),    'value' => number_format($kpis['orders']['total']),     'icon' => 'shopping-cart', 'color' => 'success'],
                ['label' => __('Active Courses'),  'value' => number_format($kpis['courses']),             'icon' => 'school',        'color' => 'warning'],
                ['label' => __('Products'),        'value' => number_format($kpis['products']),            'icon' => 'package',       'color' => 'info'],
            ];
        @endphp
        @foreach ($cards as $k)
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">{{ $k['label'] }}</p>
                            <h3 class="mb-0">{{ $k['value'] }}</h3>
                        </div>
                        <span class="avtar avtar-l bg-light-{{ $k['color'] }}"><i class="ti ti-{{ $k['icon'] }} fs-3"></i></span>
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Orders & Revenue Timeline') }}</h5>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary chart-range" data-days="7">7d</button>
                        <button type="button" class="btn btn-outline-primary chart-range active" data-days="30">30d</button>
                        <button type="button" class="btn btn-outline-primary chart-range" data-days="90">90d</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="timelineChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-crown me-1 text-warning"></i> {{ __('Top Customers') }}</h5></div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        @forelse ($topCustomers as $c)
                            <li class="d-flex justify-content-between border-bottom px-3 py-2">
                                <div>
                                    <a href="javascript:void(0)" onclick="showCustomerDetail({{ $c['id'] }})" class="text-body text-decoration-none">
                                        <strong>{{ \Illuminate\Support\Str::limit($c['name'] ?: $c['username'], 25) }}</strong>
                                    </a>
                                    <br><small class="text-muted">{{ $c['orders_count'] }} {{ __('orders') }}</small>
                                </div>
                                <strong class="text-success">{{ number_format($c['total_spent'], 0) }}</strong>
                            </li>
                        @empty
                            <li class="text-center text-muted p-4">{{ __('No data') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-school me-1 text-info"></i> {{ __('Most Enrolled Courses') }}</h5></div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        @forelse ($mostEnrolled as $c)
                            <li class="d-flex justify-content-between border-bottom px-3 py-2">
                                <div>
                                    <strong>{{ \Illuminate\Support\Str::limit($c['name'], 30) }}</strong>
                                    <br><small class="text-muted">{{ $c['instructor'] }} · {{ $c['duration'] }}</small>
                                </div>
                                <span class="badge bg-light-info align-self-center">{{ number_format($c['students_count']) }}</span>
                            </li>
                        @empty
                            <li class="text-center text-muted p-4">{{ __('No data') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-package me-1 text-success"></i> {{ __('Top Products') }}</h5></div>
                <div class="card-body p-0">
                    <ul class="list-unstyled mb-0">
                        @forelse ($topProducts as $p)
                            <li class="d-flex justify-content-between border-bottom px-3 py-2">
                                <div>
                                    <strong>{{ \Illuminate\Support\Str::limit($p['name'], 30) }}</strong>
                                    <br><small class="text-muted">{{ $p['total_sales'] }} {{ __('sold') }}</small>
                                </div>
                                <strong class="align-self-center">{{ $p['price'] ?: '—' }}</strong>
                            </li>
                        @empty
                            <li class="text-center text-muted p-4">{{ __('No data') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-rss me-1"></i> {{ __('Activity Feed') }}</h5></div>
                <div class="card-body p-0" style="max-height: 460px; overflow-y: auto;">
                    @forelse ($activity as $ev)
                        <div class="d-flex align-items-start px-3 py-2 border-bottom">
                            <span class="avtar avtar-s bg-light-{{ $ev['color'] }} me-2"><i class="ti ti-{{ $ev['icon'] }}"></i></span>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $ev['title'] }}</strong>
                                    <small class="text-muted">{{ $ev['date'] ? \Carbon\Carbon::parse($ev['date'])->diffForHumans() : '' }}</small>
                                </div>
                                <small class="text-muted">{{ $ev['subtitle'] }}</small>
                                @if (!empty($ev['badge']))
                                    <span class="badge bg-light-{{ $ev['color'] }} ms-1" style="font-size: 9px;">{{ $ev['badge'] }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center p-4 mb-0">{{ __('No activity') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-alert-triangle me-1 text-warning"></i> {{ __('Abandoned Carts') }} (>24h)</h5>
                    <span class="badge bg-light-warning">{{ count($abandoned) }}</span>
                </div>
                <div class="card-body p-0" style="max-height: 460px; overflow-y: auto;">
                    @forelse ($abandoned as $o)
                        <a href="javascript:void(0);" onclick="showOrderDetail({{ $o['id'] }})"
                           class="d-flex justify-content-between border-bottom px-3 py-2 text-decoration-none text-body">
                            <div>
                                <strong>#{{ $o['number'] }}</strong>
                                <br><small class="text-muted">{{ $o['customer_name'] ?: 'Guest' }} · {{ $o['customer_email'] }}</small>
                            </div>
                            <div class="text-end">
                                <strong>{{ number_format($o['total'], 2) }}</strong>
                                <br><small class="text-muted">{{ \Carbon\Carbon::parse($o['date_created'])->diffForHumans() }}</small>
                            </div>
                        </a>
                    @empty
                        <p class="text-muted text-center p-4 mb-0">{{ __('No abandoned carts') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Details') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    let chart;
    function loadChart(days) {
        fetch('{{ route('websites.charts.timeline') }}?days=' + days)
            .then(r => r.json())
            .then(d => {
                const ctx = document.getElementById('timelineChart').getContext('2d');
                if (chart) chart.destroy();
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: d.labels,
                        datasets: [
                            { label: 'Orders', data: d.orders, borderColor: '#4e73df', backgroundColor: 'rgba(78,115,223,0.08)', tension: 0.3, yAxisID: 'y' },
                            { label: 'Revenue', data: d.revenue, borderColor: '#1cc88a', backgroundColor: 'rgba(28,200,138,0.08)', tension: 0.3, yAxisID: 'y1' },
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y:  { type: 'linear', position: 'left',  title: { display: true, text: 'Orders' } },
                            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Revenue' } }
                        }
                    }
                });
            });
    }
    document.querySelectorAll('.chart-range').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.chart-range').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadChart(this.dataset.days);
        });
    });
    loadChart(30);

    // Detail loaders — use DOMParser + replaceChildren to avoid script execution.
    function loadIntoModal(url) {
        const body = document.getElementById('detailModalBody');
        body.replaceChildren();
        const spinner = document.createElement('div');
        spinner.className = 'text-center py-5';
        spinner.innerHTML = '<div class="spinner-border"></div>';
        body.appendChild(spinner);
        const modal = new bootstrap.Modal(document.getElementById('detailModal'));
        modal.show();
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                body.replaceChildren(...doc.body.childNodes);
                // Re-execute any inline <script> the partial relied on
                doc.body.querySelectorAll('script').forEach(old => {
                    const s = document.createElement('script');
                    if (old.src) s.src = old.src;
                    else s.textContent = old.textContent;
                    body.appendChild(s);
                });
            })
            .catch(() => { body.replaceChildren(); body.appendChild(Object.assign(document.createElement('div'), {className: 'alert alert-danger', textContent: 'Failed to load.'})); });
    }
    window.showOrderDetail    = id => loadIntoModal('/websites/orders/' + id);
    window.showCustomerDetail = id => loadIntoModal('/websites/users/' + id);
})();
</script>
@endpush
