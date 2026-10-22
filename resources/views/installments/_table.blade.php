{{-- Summary Cards - 5 Cards Only --}}
<div class="row mb-4">
    @php
        // All counts/amounts are pre-computed in the controller (full filtered set, not just current page)
        $totalAmount = $totalPaid + $totalUnpaid + $totalHopeless + $totalExemption;
        $paidPercentage = $totalAmount > 0 ? round(($totalPaid / $totalAmount) * 100) : 0;
        $unpaidPercentage = $totalAmount > 0 ? round(($totalUnpaid / $totalAmount) * 100) : 0;
        $hopelessPercentage = $totalAmount > 0 ? round(($totalHopeless / $totalAmount) * 100) : 0;
        $exemptionPercentage = $totalAmount > 0 ? round(($totalExemption / $totalAmount) * 100) : 0;
    @endphp

    {{-- 1. Total Amount Card --}}
    <div class="col-xl col-lg-4 col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-white">{{ __('Total Amount') }}</h6>
                    <i class="ti ti-currency-dollar fs-3 text-white"></i>
                </div>
                <h3 class="mb-1 text-white fw-bold">{{ \Auth::user()->priceFormat($totalAmount) }}</h3>
                <small class="text-white opacity-75">
                    <i class="ti ti-receipt"></i> {{ $installments->total() }} {{ __('Installments') }}
                </small>
            </div>
        </div>
    </div>

    {{-- 2. Total Paid Card --}}
    <div class="col-xl col-lg-4 col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background-color: #d4edda;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-success">{{ __('Total Paid') }}</h6>
                    <i class="ti ti-check fs-3 text-success"></i>
                </div>
                <h3 class="mb-1 text-success fw-bold">{{ \Auth::user()->priceFormat($totalPaid) }}</h3>
                <small class="text-success">
                    <i class="ti ti-percentage"></i> {{ $paidPercentage }}% ({{ $paidCount }} {{ __('Paid') }})
                </small>
            </div>
        </div>
    </div>

    {{-- 3. Total Unpaid Card --}}
    <div class="col-xl col-lg-4 col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background-color: #fff3cd;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-warning">{{ __('Total Unpaid') }}</h6>
                    <i class="ti ti-clock fs-3 text-warning"></i>
                </div>
                <h3 class="mb-1 text-warning fw-bold">{{ \Auth::user()->priceFormat($totalUnpaid) }}</h3>
                <small class="text-warning">
                    <i class="ti ti-percentage"></i> {{ $unpaidPercentage }}% ({{ $unpaidCount }}
                    {{ __('Unpaid') }})
                </small>
            </div>
        </div>
    </div>

    {{-- 4. Hopeless Amount Card --}}
    <div class="col-xl col-lg-4 col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background-color: #f8d7da;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-danger">{{ __('Hopeless Amount') }}</h6>
                    <i class="ti ti-alert-triangle fs-3 text-danger"></i>
                </div>
                <h3 class="mb-1 text-danger fw-bold">{{ \Auth::user()->priceFormat($totalHopeless) }}</h3>
                <small class="text-danger">
                    <i class="ti ti-ban"></i> {{ $hopelessPercentage }}% ({{ $hopelessCount }}
                    {{ __('Uncollectable') }})
                </small>
            </div>
        </div>
    </div>

    {{-- 5. Total Exemption Card --}}
    <div class="col-xl col-lg-4 col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background-color: #d1ecf1;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-info">{{ __('Total Exemption') }}</h6>
                    <i class="ti ti-discount-2 fs-3 text-info"></i>
                </div>
                <h3 class="mb-1 text-info fw-bold">{{ \Auth::user()->priceFormat($totalExemption) }}</h3>
                <small class="text-info">
                    <i class="ti ti-gift"></i> {{ $exemptionPercentage }}% ({{ $exemptionCount }}
                    {{ __('Forgiven') }})
                </small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{{ __('Lead') }}</th>
                        <th>{{ __('Deal Purchase') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Exemption') }}</th>
                        <th>{{ __('Effective') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Hopeless') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th width="200px">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($installments as $installment)
                        <tr>
                            <td>
                                @if ($installment->dealPurchase && $installment->dealPurchase->deal && $installment->dealPurchase->deal->lead)
                                    <a href="{{ route('leads.show', $installment->dealPurchase->deal->lead->id) }}"
                                        class="text-primary fw-bold">
                                        <i class="ti ti-user"></i> {{ $installment->dealPurchase->deal->lead->name }}
                                    </a>
                                    @if ($installment->dealPurchase->deal->lead->phone)
                                        <br><small
                                            class="text-muted">{{ $installment->dealPurchase->deal->lead->phone }}</small>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($installment->dealPurchase)
                                    <a href="{{ route('deal-purchase.show', $installment->dealPurchase->id) }}"
                                        class="text-info">
                                        {{ $installment->dealPurchase->deal ? $installment->dealPurchase->deal->name : '#' . $installment->dealPurchase->id }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span
                                    class="badge bg-primary fs-6">{{ \Auth::user()->priceFormat($installment->amount) }}</span>
                            </td>
                            <td>
                                @php
                                    $exemption = $installment->exemption ?? 0;
                                @endphp
                                @if ($exemption > 0)
                                    <span
                                        class="badge bg-info fs-6">{{ \Auth::user()->priceFormat($exemption) }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $effectiveAmount = $installment->amount - ($installment->exemption ?? 0);
                                @endphp
                                <strong
                                    class="text-success">{{ \Auth::user()->priceFormat($effectiveAmount) }}</strong>
                            </td>
                            <td>
                                <span class="text-muted small">
                                    <i class="ti ti-calendar"></i>
                                    {{ \Auth::user()->dateFormat($installment->date) }}
                                </span>
                            </td>
                            <td>
                                @if ($installment->status == 'paid')
                                    <span class="badge bg-success">
                                        <i class="ti ti-check"></i> {{ __('Paid') }}
                                    </span>
                                @else
                                    <span class="badge bg-warning">
                                        <i class="ti ti-clock"></i> {{ __('Unpaid') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($installment->is_hopeless)
                                    <span class="badge bg-danger">
                                        <i class="ti ti-alert-triangle"></i> {{ __('ON') }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="ti ti-circle-check"></i> {{ __('OFF') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($installment->description)
                                    <span class="text-muted small" data-bs-toggle="tooltip"
                                        title="{{ $installment->description }}">
                                        {{ Str::limit($installment->description, 25) }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="Action">
                                @can('view deal')
                                    <div class="action-btn bg-warning ms-2">
                                        <a href="{{ route('installments.show', $installment->id) }}"
                                            class="mx-3 btn btn-sm d-inline-flex align-items-center"
                                            data-bs-toggle="tooltip" title="{{ __('View') }}">
                                            <i class="ti ti-eye text-white"></i>
                                        </a>
                                    </div>
                                @endcan
                                @can('edit deal')
                                    <div class="action-btn bg-info ms-2">
                                        <a href="{{ route('installments.edit', $installment->id) }}"
                                            class="mx-3 btn btn-sm d-inline-flex align-items-center"
                                            data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                            <i class="ti ti-pencil text-white"></i>
                                        </a>
                                    </div>
                                @endcan
                                @can('delete deal')
                                    @if (!Auth::user()->hasRole('Sales Employee'))
                                        <div class="action-btn bg-danger ms-2">
                                            {!! Form::open([
                                                'method' => 'DELETE',
                                                'route' => ['installments.destroy', $installment->id],
                                                'id' => 'delete-form-' . $installment->id,
                                            ]) !!}
                                            <a href="#"
                                                class="mx-3 btn btn-sm d-inline-flex align-items-center bs-pass-para"
                                                data-bs-toggle="tooltip" title="{{ __('Delete') }}"
                                                data-original-title="{{ __('Delete') }}"
                                                data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                                data-confirm-yes="document.getElementById('delete-form-{{ $installment->id }}').submit();">
                                                <i class="ti ti-trash text-white"></i>
                                            </a>
                                            {!! Form::close() !!}
                                        </div>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($installments->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $installments->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
