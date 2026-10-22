<div class="row g-3 mb-3">
    <div class="col-md-3 text-center">
        @if (!empty($customer['avatar']))
            <img src="{{ $customer['avatar'] }}" class="rounded-circle mb-2" width="96" height="96" alt="">
        @else
            <span class="avtar avtar-xl bg-light-secondary mb-2 d-inline-flex"><i class="ti ti-user fs-1"></i></span>
        @endif
        @if ($customer['is_paying'])
            <span class="badge bg-light-success d-block">{{ __('Paying Customer') }}</span>
        @else
            <span class="badge bg-light-secondary d-block">{{ __('Lead') }}</span>
        @endif
    </div>
    <div class="col-md-9">
        <h4 class="mb-1">{{ $customer['name'] ?: $customer['username'] ?: '(no name)' }}</h4>
        <p class="text-muted mb-2">{{ $customer['email'] }}</p>
        <div class="row g-2">
            <div class="col-md-4 border-end">
                <small class="text-muted d-block">{{ __('Total Spent') }}</small>
                <strong class="text-success fs-5">{{ number_format($customer['total_spent'], 2) }}</strong>
            </div>
            <div class="col-md-4 border-end">
                <small class="text-muted d-block">{{ __('Orders') }}</small>
                <strong class="fs-5">{{ number_format($customer['orders_count']) }}</strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">{{ __('Member Since') }}</small>
                <strong>{{ !empty($customer['date_created']) ? \Carbon\Carbon::parse($customer['date_created'])->format('Y-m-d') : '—' }}</strong>
            </div>
        </div>
    </div>
</div>

<hr>

<div class="row g-3">
    <div class="col-md-6">
        <h6 class="text-muted mb-2"><i class="ti ti-receipt me-1"></i> {{ __('Billing Address') }}</h6>
        @if (!empty($customer['billing']))
            <address class="mb-0">
                {{ ($customer['billing']['first_name'] ?? '') . ' ' . ($customer['billing']['last_name'] ?? '') }}<br>
                @if (!empty($customer['billing']['company'])){{ $customer['billing']['company'] }}<br>@endif
                @if (!empty($customer['billing']['address_1'])){{ $customer['billing']['address_1'] }}<br>@endif
                @if (!empty($customer['billing']['address_2'])){{ $customer['billing']['address_2'] }}<br>@endif
                {{ ($customer['billing']['city'] ?? '') }} {{ ($customer['billing']['postcode'] ?? '') }}<br>
                {{ $customer['billing']['country'] ?? '' }}<br>
                @if (!empty($customer['billing']['phone']))<i class="ti ti-phone"></i> {{ $customer['billing']['phone'] }}@endif
            </address>
        @else
            <p class="text-muted small mb-0">{{ __('No billing address') }}</p>
        @endif
    </div>
    <div class="col-md-6">
        <h6 class="text-muted mb-2"><i class="ti ti-truck me-1"></i> {{ __('Shipping Address') }}</h6>
        @if (!empty($customer['shipping']) && (!empty($customer['shipping']['address_1']) || !empty($customer['shipping']['city'])))
            <address class="mb-0">
                {{ ($customer['shipping']['first_name'] ?? '') . ' ' . ($customer['shipping']['last_name'] ?? '') }}<br>
                @if (!empty($customer['shipping']['address_1'])){{ $customer['shipping']['address_1'] }}<br>@endif
                {{ ($customer['shipping']['city'] ?? '') }} {{ ($customer['shipping']['postcode'] ?? '') }}<br>
                {{ $customer['shipping']['country'] ?? '' }}
            </address>
        @else
            <p class="text-muted small mb-0">{{ __('Same as billing') }}</p>
        @endif
    </div>
</div>
