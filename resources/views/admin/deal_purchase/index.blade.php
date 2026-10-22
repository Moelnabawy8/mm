@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Deal Purchase') }}
@endsection
@section('title')
    {{ __('Manage Deal Purchase') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Deal Purchase') }}</li>
@endsection

@push('script-page')
    <script>
        var DP_FILTER_KEY = 'crm_deal_purchase_filter';

        function dpFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            active ? $btn.removeClass('btn-success').addClass('btn-warning') : $btn.removeClass('btn-warning').addClass('btn-success');
        }

        function hasDpActiveFilter(data) {
            return Object.keys(data).some(function(k) {
                // deal_name is only the label shown in the Deal picker, not a filter.
                return k !== 'deal_name' && data[k] !== '' && data[k] != null;
            });
        }

        $(document).ready(function() {
            if ($("#filter_date_from").length > 0) $("#filter_date_from").flatpickr({});
            if ($("#filter_date_to").length > 0) $("#filter_date_to").flatpickr({});

            var _saved = localStorage.getItem(DP_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.deal_id) {
                        $('#filter_deal').val(_sf.deal_id);
                        if (_sf.deal_name) $('#filter_deal_search').val(_sf.deal_name);
                    }
                    if (_sf.status) $('#filter_status').val(_sf.status);
                    if (_sf.employee_id) $('#filter_employee').val(_sf.employee_id).trigger('change');
                    if (_sf.min_price) $('#filter_min_price').val(_sf.min_price);
                    if (_sf.max_price) $('#filter_max_price').val(_sf.max_price);
                    if (_sf.date_from) { var _fp1 = document.getElementById('filter_date_from')._flatpickr; if (_fp1) _fp1.setDate(_sf.date_from, false); }
                    if (_sf.date_to) { var _fp2 = document.getElementById('filter_date_to')._flatpickr; if (_fp2) _fp2.setDate(_sf.date_to, false); }
                    if (hasDpActiveFilter(_sf)) { dpFilterBadge(true); $('#apply-filter').trigger('click'); }
                } catch(e) { localStorage.removeItem(DP_FILTER_KEY); }
            }
        });

        $(document).on('click', '#apply-filter', function() {
            var data = {
                deal_id: $('#filter_deal').val(),
                deal_name: $('#filter_deal_search').val(),
                status: $('#filter_status').val(),
                employee_id: $('#filter_employee').val(),
                min_price: $('#filter_min_price').val(),
                max_price: $('#filter_max_price').val(),
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
            };
            localStorage.setItem(DP_FILTER_KEY, JSON.stringify(data));

            // deal_name is a display label for the picker; it must not reach the server
            // or it ends up in every pagination link via appends($request->query()).
            var payload = $.extend({}, data);
            delete payload.deal_name;

            window.currentDpFilterData = payload;
            dpFilterBadge(hasDpActiveFilter(data));
            $.ajax({
                url: '{{ route('deal-purchase.index') }}',
                type: 'GET',
                data: payload,
                success: function(response) {
                    $('#deal-purchase-table-container').html(response);
                    var $meta = $('#filter-metadata');
                    var currency = $meta.data('currency') || '';
                    $('#card-total-deals').text($meta.data('total-count'));
                    $('#card-total-price').text(currency + ' ' + $meta.data('total-price'));
                    $('#card-total-paid').text(currency + ' ' + $meta.data('total-paid'));
                    $('#card-total-remaining').text(currency + ' ' + $meta.data('total-remaining'));
                }
            });
            $('#advancedFilterModal').modal('hide');
        });

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(DP_FILTER_KEY);
            window.currentDpFilterData = {};
            window.location.reload();
        });

        $(document).on('click', '#deal-purchase-table-container .pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            $.ajax({
                url: url, type: 'GET', data: window.currentDpFilterData || {},
                success: function(response) {
                    $('#deal-purchase-table-container').html(response);
                    var $meta = $('#filter-metadata');
                    var currency = $meta.data('currency') || '';
                    $('#card-total-deals').text($meta.data('total-count'));
                    $('#card-total-price').text(currency + ' ' + $meta.data('total-price'));
                    $('#card-total-paid').text(currency + ' ' + $meta.data('total-paid'));
                    $('#card-total-remaining').text(currency + ' ' + $meta.data('total-remaining'));
                    $('html, body').animate({ scrollTop: $('#deal-purchase-table-container').offset().top - 80 }, 200);
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    @can('create deal')
        <div class="col-12 text-end btn btn-sm">
            <a href="#" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#advancedFilterModal"
                title="{{ __('Advanced Filter') }}" data-bs-original-title="{{ __('Advanced Filter') }}">
                <i class="ti ti-filter"></i>
            </a>
            <a href="{{ route('deal-purchase.create') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                title="{{ __('Create') }}">
                <i class="ti ti-plus"></i>
            </a>
        </div>
    @endcan
@endsection
@section('content')
    <div class="row mb-4 gy-3" id="summary-cards">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-2 text-muted">{{ __('Total Deals') }}</h6>
                            <h3 class="mb-0" id="card-total-deals">{{ $totalCount }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-primary">
                            <i class="ti ti-shopping-cart text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-2 text-muted">{{ __('Total Price') }}</h6>
                            <h3 class="mb-0" id="card-total-price">{{ $defaultCurrency }}
                                {{ number_format($grandTotalPrice, 2) }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-info">
                            <i class="ti ti-currency-dollar text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-2 text-muted">{{ __('Total Paid Amount') }}</h6>
                            <h3 class="mb-0" id="card-total-paid">{{ $defaultCurrency }}
                                {{ number_format($grandTotalPaid, 2) }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-success">
                            <i class="ti ti-check text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-2 text-muted">{{ __('Total Remaining Amount') }}</h6>
                            <h3 class="mb-0" id="card-total-remaining">{{ $defaultCurrency }}
                                {{ number_format($grandTotalRemaining, 2) }}</h3>
                        </div>
                        <div class="avatar avatar-md bg-warning">
                            <i class="ti ti-clock text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div id="deal-purchase-table-container">
                        @include('admin.deal_purchase._table')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filter Modal -->
    <div class="modal fade" id="advancedFilterModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{ __('Advanced Filter') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="filter-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_deal_search" class="form-label">{{ __('Deal') }}</label>
                                    @include('sales_meetings._search_picker', [
                                        'pickerId' => 'filter_deal',
                                        'fieldName' => 'deal_id',
                                        'url' => route('deal-purchase.deals.search'),
                                        'selectedId' => request('deal_id'),
                                        'selectedName' => $selectedDealName ?? '',
                                        'placeholder' => __('Search Deal'),
                                    ])
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_status" class="form-label">{{ __('Status') }}</label>
                                    <select class="form-control select2" id="filter_status" name="status">
                                        <option value="">{{ __('Select Status') }}</option>
                                        <option value="pending">{{ __('Pending') }}</option>
                                        <option value="complete">{{ __('Complete') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="filter_employee" class="form-label">{{ __('Employee') }}</label>
                                    <select class="form-control select2" id="filter_employee" name="employee_id">
                                        <option value="">{{ __('Select Employee') }}</option>
                                        @foreach ($employees as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_min_price" class="form-label">{{ __('Min Price') }}</label>
                                    <input type="number" class="form-control" id="filter_min_price" name="min_price"
                                        step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_max_price" class="form-label">{{ __('Max Price') }}</label>
                                    <input type="number" class="form-control" id="filter_max_price" name="max_price"
                                        step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_date_from" class="form-label">{{ __('Date From') }}</label>
                                    <input type="text" class="form-control" id="filter_date_from" name="date_from"
                                        placeholder="YYYY-MM-DD" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_date_to" class="form-label">{{ __('Date To') }}</label>
                                    <input type="text" class="form-control" id="filter_date_to" name="date_to"
                                        placeholder="YYYY-MM-DD" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="clear-filter">{{ __('Clear') }}</button>
                    <button type="button" class="btn btn-primary" id="apply-filter">{{ __('Apply') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection
