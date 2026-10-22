{{-- Totals are pre-computed server-side for all filtered records --}}

<div id="filter-metadata"
    data-total-count="{{ $totalCount ?? $dealPurchases->count() }}"
    data-total-price="{{ number_format($grandTotalPrice, 2) }}"
    data-total-paid="{{ number_format($grandTotalPaid, 2) }}"
    data-total-remaining="{{ number_format($grandTotalRemaining, 2) }}"
    data-currency="{{ $defaultCurrency }}"
    style="display:none;"></div>

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>{{ __('Deal') }}</th>
                <th>{{ __('Lead') }}</th>
                <th>{{ __('Assigned Staff') }}</th>
                <th>{{ __('Price') }}</th>
                <th>{{ __('Paid Amount') }}</th>
                <th>{{ __('Remaining Amount') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Attachments') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Time') }}</th>
                <th width="200px">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dealPurchases as $purchase)
                @php
                    // Same rule as the detail page: upfront payment + installments
                    // already marked paid. paid_installments_sum comes from withSum().
                    $paidInstallments =
                        $purchase->payment_type == 'installment'
                            ? (float) ($purchase->paid_installments_sum ?? 0)
                            : 0;
                    $totalPaid = (float) $purchase->paid_amount + $paidInstallments;
                    $remainingAmount = max(0, (float) $purchase->price - $totalPaid);
                @endphp
                <tr class="font-style">
                    <td>
                        @if (!empty($purchase->deal))
                            <a href="{{ route('deals.show', $purchase->deal->id) }}" class="text-primary">
                                {{ $purchase->deal->name }}
                            </a>
                        @endif
                    </td>
                    <td>
                        @if (!empty($purchase->deal) && !empty($purchase->deal->lead))
                            <a href="{{ route('leads.show', $purchase->deal->lead->id) }}" class="text-primary">
                                {{ $purchase->deal->lead->name }}
                            </a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        {{-- Read from the user_leads pivot, the same source the lead page and
                             the leads list use. This column used to read leads.user_id, a
                             separate column that can point at a different person, so the two
                             screens named different staff for the same lead. --}}
                        @forelse (!empty($purchase->deal) && !empty($purchase->deal->lead) ? $purchase->deal->lead->users : [] as $user)
                            <span class="d-inline-flex align-items-center me-2">
                                <img alt="{{ $user->name }}"
                                    @if ($user->avatar) src="{{ asset('/storage/uploads/avatar/' . $user->avatar) }}" @else src="{{ asset('/storage/uploads/avatar/avatar.png') }}" @endif
                                    class="rounded-circle me-1" width="25" height="25">
                                <span dir="auto">{{ $user->name }}</span>
                            </span>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td>{{ $purchase->currency }} {{ number_format($purchase->price, 2) }}</td>
                    <td>{{ $purchase->currency }} {{ number_format($totalPaid, 2) }}</td>
                    <td>{{ $purchase->currency }} {{ number_format($remainingAmount, 2) }}</td>
                    <td>{{ ucfirst($purchase->status) }}</td>
                    <td>
                        @php($attachmentCount = $purchase->attachments->count())
                        @if ($attachmentCount > 0)
                            <span class="badge bg-info rounded-pill">
                                <i class="ti ti-paperclip"></i> {{ $attachmentCount }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ \Auth::user()->dateFormat($purchase->purchase_date) }}</td>
                    <td>{{ \Auth::user()->timeFormat($purchase->created_at) }}</td>
                    <td class="Action">
                        @can('view deal')
                            <div class="action-btn bg-warning ms-2">
                                <a href="{{ route('deal-purchase.show', $purchase->id) }}"
                                    class="mx-3 btn btn-sm d-inline-flex align-items-center" data-bs-toggle="tooltip"
                                    title="{{ __('View') }}">
                                    <i class="ti ti-eye text-white"></i>
                                </a>
                            </div>
                        @endcan
                        @can('edit deal')
                            <div class="action-btn bg-info ms-2">
                                <a href="{{ route('deal-purchase.edit', $purchase->id) }}"
                                    class="mx-3 btn btn-sm d-inline-flex align-items-center" data-bs-toggle="tooltip"
                                    title="{{ __('Edit') }}">
                                    <i class="ti ti-pencil text-white"></i>
                                </a>
                            </div>
                        @endcan
                        @can('delete deal')
                            @if (!Auth::user()->hasRole('Sales Employee'))
                                <div class="action-btn bg-danger ms-2">
                                    {!! Form::open([
                                        'method' => 'DELETE',
                                        'route' => ['deal-purchase.destroy', $purchase->id],
                                        'id' => 'delete-form-' . $purchase->id,
                                    ]) !!}
                                    <a href="#" class="mx-3 btn btn-sm d-inline-flex align-items-center bs-pass-para"
                                        data-bs-toggle="tooltip" title="{{ __('Delete') }}"
                                        data-original-title="{{ __('Delete') }}"
                                        data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                        data-confirm-yes="document.getElementById('delete-form-{{ $purchase->id }}').submit();">
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

@if($dealPurchases->hasPages())
<div class="d-flex justify-content-center mt-3 mb-2">
    {{ $dealPurchases->links('pagination::bootstrap-5') }}
</div>
@endif

<!-- Fixed Totals Footer -->
<div class="card mt-3 border-primary">
    <div class="card-body py-3">
        @if (!empty($mixedCurrency))
            <div class="alert alert-warning py-2 mb-3 small" id="mixed-currency-warning" role="alert">
                <i class="ti ti-alert-triangle me-1"></i>
                {{ __('These records use more than one currency, so the totals below add different currencies together. Filter by a single currency to get a meaningful total.') }}
            </div>
        @endif
        <div class="row align-items-center">
            <div class="col-md-3">
                <h6 class="mb-0 text-primary"><i class="ti ti-calculator me-2"></i>{{ __('Total') }}</h6>
            </div>
            <div class="col-md-3">
                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                    <span class="text-muted">{{ __('Total Price') }}:</span>
                    <strong id="footer-total-price" class="text-dark">{{ $defaultCurrency }}
                        {{ number_format($grandTotalPrice, 2) }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex justify-content-between align-items-center p-2 bg-success bg-opacity-10 rounded">
                    <span class="text-muted">{{ __('Paid Amount') }}:</span>
                    <strong id="footer-total-paid" class="text-success">{{ $defaultCurrency }}
                        {{ number_format($grandTotalPaid, 2) }}</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="d-flex justify-content-between align-items-center p-2 bg-danger bg-opacity-10 rounded">
                    <span class="text-muted">{{ __('Remaining Amount') }}:</span>
                    <strong id="footer-total-remaining" class="text-danger">{{ $defaultCurrency }}
                        {{ number_format($grandTotalRemaining, 2) }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
