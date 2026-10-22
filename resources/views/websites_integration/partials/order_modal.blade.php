@php
    $statusColor = ['pending'=>'warning','processing'=>'info','on-hold'=>'secondary','completed'=>'success','cancelled'=>'danger','refunded'=>'danger','failed'=>'danger'][$order['status']] ?? 'secondary';
@endphp

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">{{ __('Order') }} #{{ $order['number'] }}</h4>
        <small class="text-muted">{{ \Carbon\Carbon::parse($order['date_created'])->format('Y-m-d H:i') }}</small>
        <span class="badge bg-light-{{ $statusColor }} text-capitalize ms-2">{{ $order['status'] }}</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="ti ti-edit me-1"></i>{{ __('Change Status') }}
            </button>
            <ul class="dropdown-menu">
                @foreach (['pending','processing','on-hold','completed','cancelled','refunded'] as $s)
                    @if ($s !== $order['status'])
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="changeOrderStatus({{ $order['id'] }}, '{{ $s }}')">
                            <i class="ti ti-arrow-right me-1"></i> {{ ucfirst($s) }}
                        </a></li>
                    @endif
                @endforeach
            </ul>
        </div>
        <button class="btn btn-sm btn-outline-warning" onclick="addOrderNote({{ $order['id'] }})">
            <i class="ti ti-note me-1"></i>{{ __('Add Note') }}
        </button>
        @if (!in_array($order['status'], ['refunded', 'cancelled', 'failed']))
            <button class="btn btn-sm btn-outline-danger" onclick="refundOrder({{ $order['id'] }}, {{ $order['total'] }}, '{{ $order['currency'] }}')">
                <i class="ti ti-refresh-alert me-1"></i>{{ __('Refund') }}
            </button>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <h6 class="text-muted mb-2"><i class="ti ti-user me-1"></i> {{ __('Customer') }}</h6>
        <div>
            <strong>{{ $order['customer_name'] ?: '(guest)' }}</strong><br>
            <small><i class="ti ti-mail"></i> {{ $order['customer_email'] ?: '—' }}</small><br>
            @if (!empty($order['customer_phone']))<small><i class="ti ti-phone"></i> {{ $order['customer_phone'] }}</small>@endif
        </div>
    </div>
    <div class="col-md-6">
        <h6 class="text-muted mb-2"><i class="ti ti-credit-card me-1"></i> {{ __('Payment') }}</h6>
        <div>
            <strong>{{ $order['payment_method'] ?: '—' }}</strong>
            @if (!empty($order['transaction_id']))<br><small class="text-muted">TX: {{ $order['transaction_id'] }}</small>@endif
        </div>
    </div>
</div>

<h6 class="text-muted mb-2"><i class="ti ti-list me-1"></i> {{ __('Line Items') }}</h6>
<div class="table-responsive mb-3">
    <table class="table table-sm">
        <thead><tr><th>{{ __('Product') }}</th><th class="text-center">{{ __('Qty') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
        <tbody>
            @forelse ($order['line_items'] ?? [] as $li)
                <tr>
                    <td>{{ $li['name'] }}</td>
                    <td class="text-center">{{ $li['quantity'] }}</td>
                    <td class="text-end">{{ number_format($li['price'], 2) }}</td>
                    <td class="text-end"><strong>{{ number_format($li['total'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">{{ __('No line items') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-end">{{ __('Subtotal') }}:</td><td class="text-end">{{ number_format($order['subtotal'], 2) }}</td></tr>
            @if ($order['shipping_total'] > 0)<tr><td colspan="3" class="text-end">{{ __('Shipping') }}:</td><td class="text-end">{{ number_format($order['shipping_total'], 2) }}</td></tr>@endif
            @if ($order['discount_total'] > 0)<tr><td colspan="3" class="text-end">{{ __('Discount') }}:</td><td class="text-end text-danger">-{{ number_format($order['discount_total'], 2) }}</td></tr>@endif
            <tr><td colspan="3" class="text-end"><strong>{{ __('Total') }}:</strong></td><td class="text-end"><strong class="fs-5">{{ number_format($order['total'], 2) }} {{ $order['currency'] }}</strong></td></tr>
        </tfoot>
    </table>
</div>

@if (!empty($order['customer_note']))
    <div class="alert alert-info mb-3">
        <i class="ti ti-message me-1"></i> <strong>{{ __('Customer Note') }}:</strong> {{ $order['customer_note'] }}
    </div>
@endif

<h6 class="text-muted mb-2"><i class="ti ti-history me-1"></i> {{ __('Order Notes') }} ({{ count($notes) }})</h6>
<div style="max-height: 300px; overflow-y: auto;">
    @forelse ($notes as $n)
        <div class="border-start border-{{ $n['customer_note'] ? 'success' : 'secondary' }} ps-2 mb-2">
            <small class="text-muted">{{ $n['author'] }} · {{ \Carbon\Carbon::parse($n['date_created'])->diffForHumans() }}
                @if ($n['customer_note'])<span class="badge bg-light-success ms-1" style="font-size: 9px;">{{ __('visible to customer') }}</span>@endif
            </small>
            <p class="mb-0 small">{{ $n['note'] }}</p>
        </div>
    @empty
        <p class="text-muted small mb-0">{{ __('No notes yet') }}</p>
    @endforelse
</div>
