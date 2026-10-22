@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Installments') }}
@endsection

@section('title')
    {{ __('Manage Installments') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Installments') }}</li>
@endsection

@push('script-page')
    <script>
        var INST_FILTER_KEY = 'crm_installments_filter';

        function instFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            active ? $btn.removeClass('btn-success').addClass('btn-warning') : $btn.removeClass('btn-warning').addClass('btn-success');
        }

        function hasInstActiveFilter(data) {
            return Object.values(data).some(function(v) { return v !== '' && v != null; });
        }

        $(document).ready(function() {
            if ($("#filter_date_from").length > 0) $("#filter_date_from").flatpickr({});
            if ($("#filter_date_to").length > 0) $("#filter_date_to").flatpickr({});

            var _saved = localStorage.getItem(INST_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.lead_id) $('#filter_lead').val(_sf.lead_id).trigger('change');
                    if (_sf.deal_purchase_id) $('#filter_deal_purchase').val(_sf.deal_purchase_id).trigger('change');
                    if (_sf.status) $('#filter_status').val(_sf.status);
                    if (_sf.min_amount) $('#filter_min_amount').val(_sf.min_amount);
                    if (_sf.max_amount) $('#filter_max_amount').val(_sf.max_amount);
                    if (_sf.date_from) { var _fp1 = document.getElementById('filter_date_from')._flatpickr; if (_fp1) _fp1.setDate(_sf.date_from, false); }
                    if (_sf.date_to) { var _fp2 = document.getElementById('filter_date_to')._flatpickr; if (_fp2) _fp2.setDate(_sf.date_to, false); }
                    if (hasInstActiveFilter(_sf)) { instFilterBadge(true); window.currentInstFilterData = _sf; $('#apply-filter').trigger('click'); }
                } catch(e) { localStorage.removeItem(INST_FILTER_KEY); }
            }
        });

        $(document).on('click', '#apply-filter', function() {
            var data = {
                lead_id: $('#filter_lead').val(),
                deal_purchase_id: $('#filter_deal_purchase').val(),
                status: $('#filter_status').val(),
                min_amount: $('#filter_min_amount').val(),
                max_amount: $('#filter_max_amount').val(),
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
            };
            localStorage.setItem(INST_FILTER_KEY, JSON.stringify(data));
            window.currentInstFilterData = data;
            instFilterBadge(hasInstActiveFilter(data));
            $.ajax({
                url: '{{ route('installments.index') }}',
                type: 'GET',
                data: data,
                success: function(response) { $('#installments-table-container').html(response); }
            });
            $('#advancedFilterModal').modal('hide');
        });

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(INST_FILTER_KEY);
            window.currentInstFilterData = {};
            window.location.reload();
        });

        $(document).on('click', '#installments-table-container .pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            $.ajax({
                url: url, type: 'GET', data: window.currentInstFilterData || {},
                success: function(response) {
                    $('#installments-table-container').html(response);
                    $('html, body').animate({ scrollTop: $('#installments-table-container').offset().top - 80 }, 200);
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
            <a href="{{ route('installments.create') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                title="{{ __('Create') }}">
                <i class="ti ti-plus"></i>
            </a>
        </div>
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div id="installments-table-container">
                @include('installments._table')
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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filter_lead" class="form-label">{{ __('Lead/Client') }}</label>
                                    <select class="form-control select2" id="filter_lead" name="lead_id">
                                        <option value="">{{ __('All Leads') }}</option>
                                        @php
                                            $leads = \App\Models\Lead::where(
                                                'created_by',
                                                \Auth::user()->ownerId(),
                                            )->get();
                                        @endphp
                                        @foreach ($leads as $lead)
                                            <option value="{{ $lead->id }}">{{ $lead->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filter_deal_purchase" class="form-label">{{ __('Deal Purchase') }}</label>
                                    <select class="form-control select2" id="filter_deal_purchase" name="deal_purchase_id">
                                        <option value="">{{ __('All Deal Purchases') }}</option>
                                        @foreach ($dealPurchases as $dp)
                                            <option value="{{ $dp->id }}">
                                                {{ $dp->deal ? $dp->deal->name : '#' . $dp->id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filter_status" class="form-label">{{ __('Status') }}</label>
                                    <select class="form-control select2" id="filter_status" name="status">
                                        <option value="">{{ __('All Statuses') }}</option>
                                        <option value="paid">{{ __('Paid') }}</option>
                                        <option value="unpaid">{{ __('Unpaid') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_min_amount" class="form-label">{{ __('Min Amount') }}</label>
                                    <input type="number" class="form-control" id="filter_min_amount" name="min_amount"
                                        step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_max_amount" class="form-label">{{ __('Max Amount') }}</label>
                                    <input type="number" class="form-control" id="filter_max_amount" name="max_amount"
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
