@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Orders') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Orders') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('websites.orders.export') }}" class="btn btn-sm btn-outline-success">
            <i class="ti ti-download me-1"></i> {{ __('Export') }}
        </a>
    </div>
@endsection

@section('content')
    @php
        $statusMeta = [
            ['key' => 'pending',    'label' => __('Pending'),    'color' => 'warning'],
            ['key' => 'processing', 'label' => __('Processing'), 'color' => 'info'],
            ['key' => 'completed',  'label' => __('Completed'),  'color' => 'success'],
            ['key' => 'refunded',   'label' => __('Refunded'),   'color' => 'danger'],
        ];
    @endphp
    <div class="row">
        @foreach ($statusMeta as $s)
            <div class="col-xl-3 col-md-6">
                <div class="card"><div class="card-body">
                    <p class="text-muted mb-1">{{ $s['label'] }}</p>
                    <h3 class="mb-0 text-{{ $s['color'] }}">{{ number_format($stats[$s['key']] ?? 0) }}</h3>
                </div></div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">{{ __('Orders') }} <span class="badge bg-light-secondary ms-1">{{ number_format($total) }}</span></h5>
                    <form method="GET" action="{{ route('websites.orders') }}" class="d-flex gap-2 mb-0 flex-wrap">
                        <select name="status" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                            <option value="">{{ __('All Statuses') }}</option>
                            @foreach (['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded'] as $s)
                                <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('Search…') }}" style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
                        @if ($search || $status)
                            <a href="{{ route('websites.orders') }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-x"></i></a>
                        @endif
                    </form>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Order #') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Items') }}</th>
                                    <th>{{ __('Total') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Payment') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orders as $o)
                                    @php
                                        $statusColor = ['pending'=>'warning','processing'=>'info','on-hold'=>'secondary','completed'=>'success','cancelled'=>'danger','refunded'=>'danger','failed'=>'danger'][$o['status']] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td><strong>#{{ $o['number'] }}</strong></td>
                                        <td>
                                            {{ $o['customer_name'] ?: '(guest)' }}
                                            @if (!empty($o['customer_email']))<br><small class="text-muted">{{ $o['customer_email'] }}</small>@endif
                                        </td>
                                        <td><span class="badge bg-light-secondary">{{ $o['items_count'] }}</span></td>
                                        <td><strong>{{ number_format($o['total'], 2) }} {{ $o['currency'] }}</strong></td>
                                        <td><span class="badge bg-light-{{ $statusColor }} text-capitalize">{{ $o['status'] }}</span></td>
                                        <td><small>{{ $o['payment_method'] ?: '—' }}</small></td>
                                        <td><small>{{ $o['date_created'] ? \Carbon\Carbon::parse($o['date_created'])->format('Y-m-d H:i') : '—' }}</small></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="showOrderDetail({{ $o['id'] }})" title="{{ __('Details') }}">
                                                <i class="ti ti-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-5">
                                        <i class="ti ti-shopping-cart-off fs-1 d-block mb-2 opacity-50"></i>
                                        {{ __('No orders match the filter.') }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @include('websites_integration.partials.pagination', ['currentPage' => $currentPage, 'pages' => $pages, 'route' => 'websites.orders', 'extra' => ['q' => $search, 'status' => $status]])
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Order Details') }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
                </div>
            </div>
        </div>
    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('script-page')
<script>
window.showOrderDetail = function(id) {
    const body = document.getElementById('detailModalBody');
    body.replaceChildren();
    const wrap = document.createElement('div'); wrap.className = 'text-center py-5';
    const sp = document.createElement('div'); sp.className = 'spinner-border text-primary';
    wrap.appendChild(sp); body.appendChild(wrap);
    new bootstrap.Modal(document.getElementById('detailModal')).show();
    fetch('/websites/orders/' + id).then(r => r.text()).then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        body.replaceChildren(...doc.body.childNodes);
    });
};

window.changeOrderStatus = function(id, newStatus) {
    if (!confirm('Change order #' + id + ' status to ' + newStatus + '?')) return;
    fetch('/websites/orders/' + id + '/status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
        body: JSON.stringify({status: newStatus}),
    })
    .then(r => r.json())
    .then(j => { if (j.ok) location.reload(); else alert('Failed: ' + (j.error || 'unknown')); });
};

window.refundOrder = function(id, total, currency) {
    const amount = prompt('Refund amount (' + currency + '). Max ' + total + ':', total);
    if (!amount || parseFloat(amount) <= 0) return;
    const reason = prompt('Reason (optional):', '');
    if (!confirm('Refund ' + amount + ' ' + currency + ' for order #' + id + '?')) return;
    fetch('/websites/orders/' + id + '/refund', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
        body: JSON.stringify({amount: parseFloat(amount), reason: reason || ''}),
    })
    .then(r => r.json())
    .then(j => { if (j.ok) location.reload(); else alert('Failed: ' + (j.error || 'unknown')); });
};

window.addOrderNote = function(id) {
    const note = prompt('Add note to order #' + id + ':');
    if (!note) return;
    const customerVisible = confirm('Make this note visible to customer? (OK = yes, Cancel = private)');
    fetch('/websites/orders/' + id + '/note', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
        body: JSON.stringify({note: note, customer_note: customerVisible}),
    })
    .then(r => r.json())
    .then(j => { if (j.ok) showOrderDetail(id); else alert('Failed: ' + (j.error || 'unknown')); });
};
</script>
@endpush
