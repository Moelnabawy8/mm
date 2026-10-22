@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Deals') }}
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/summernote/summernote-bs4.css') }}">
@endpush
@push('script-page')
    <script src="{{ asset('css/summernote/summernote-bs4.js') }}"></script>
    <script>
        var DEALS_FILTER_KEY = 'crm_deals_filter';

        function dealsFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            if (active) {
                $btn.removeClass('btn-success').addClass('btn-warning');
            } else {
                $btn.removeClass('btn-warning').addClass('btn-success');
            }
        }

        function hasDealsActiveFilter(data) {
            return Object.values(data).some(function(v) { return v !== '' && v != null; });
        }

        // Init datepicker
        $(document).ready(function() {
            if ($("#filter_date_from").length > 0) {
                $("#filter_date_from").flatpickr({});
            }
            if ($("#filter_date_to").length > 0) {
                $("#filter_date_to").flatpickr({});
            }

            // Restore saved filter on page load
            var _saved = localStorage.getItem(DEALS_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.name) $('#filter_name').val(_sf.name);
                    if (_sf.lead_id) $('#filter_lead').val(_sf.lead_id);
                    if (_sf.lead_name) $('#filter_lead_search').val(_sf.lead_name);
                    if (_sf.stage_id) $('#filter_stage').val(_sf.stage_id).trigger('change');
                    if (_sf.user_id) $('#filter_user').val(_sf.user_id).trigger('change');
                    if (_sf.products) $('#filter_products').val(_sf.products.split(',')).trigger('change');
                    if (_sf.date_from) {
                        var _fp1 = document.getElementById('filter_date_from')._flatpickr;
                        if (_fp1) _fp1.setDate(_sf.date_from, false);
                        else $('#filter_date_from').val(_sf.date_from);
                    }
                    if (_sf.date_to) {
                        var _fp2 = document.getElementById('filter_date_to')._flatpickr;
                        if (_fp2) _fp2.setDate(_sf.date_to, false);
                        else $('#filter_date_to').val(_sf.date_to);
                    }
                    if (hasDealsActiveFilter(_sf)) {
                        window.currentDealsFilterData = _sf;
                        dealsFilterBadge(true);
                        $('#apply-filter').trigger('click');
                    }
                } catch(e) {
                    localStorage.removeItem(DEALS_FILTER_KEY);
                }
            }
        });

        $(document).on('click', '#apply-filter', function() {
            var data = {
                name: $('#filter_name').val(),
                lead_id: $('#filter_lead').val(),
                lead_name: $('#filter_lead').val() ? $('#filter_lead_search').val() : '',
                stage_id: $('#filter_stage').val(),
                user_id: $('#filter_user').val(),
                products: $('#filter_products').val() ? $('#filter_products').val().join(',') : '',
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
            };

            localStorage.setItem(DEALS_FILTER_KEY, JSON.stringify(data));
            window.currentDealsFilterData = data;
            dealsFilterBadge(hasDealsActiveFilter(data));

            $.ajax({
                url: '{{ route('deals.index') }}',
                type: 'GET',
                data: data,
                success: function(response) {
                    $('#deals-table-container').html(response);
                }
            });
            $('#advancedFilterModal').modal('hide');
        });

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(DEALS_FILTER_KEY);
            window.currentDealsFilterData = {};
            window.location.reload();
        });

        // Intercept pagination clicks — keep filter active across pages
        $(document).on('click', '#deals-table-container .pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            $.ajax({
                url: url,
                type: 'GET',
                data: window.currentDealsFilterData || {},
                success: function(response) {
                    $('#deals-table-container').html(response);
                    $('html, body').animate({ scrollTop: $('#deals-table-container').offset().top - 80 }, 200);
                }
            });
        });
    </script>
@endpush

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Deal') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="#" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#advancedFilterModal"
            title="{{ __('Advanced Filter') }}" data-bs-original-title="{{ __('Advanced Filter') }}">
            <i class="ti ti-filter"></i>
        </a>
        @can('create deal')
            <a href="#" data-size="lg" data-url="{{ route('deals.create') }}" data-ajax-popup="true"
                data-bs-toggle="tooltip" title="{{ __('Create New Deal') }}" class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div id="deals-table-container">
                        @include('deals._table')
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
                                    <label for="filter_name" class="form-label">{{ __('Name') }}</label>
                                    <input type="text" class="form-control" id="filter_name" name="name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_lead" class="form-label">{{ __('Lead') }}</label>
                                    @include('deals._lead_picker', [
                                        'pickerId' => 'filter_lead',
                                        'fieldName' => 'lead_id',
                                        'selectedId' => $selectedLead->id ?? null,
                                        'selectedName' => $selectedLead->name ?? '',
                                    ])
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_stage" class="form-label">{{ __('Status') }}</label>
                                    <select class="form-control select2" id="filter_stage" name="stage_id">
                                        <option value="">{{ __('Select Status') }}</option>
                                        @foreach ($stages as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_user" class="form-label">{{ __('Employee') }}</label>
                                    <select class="form-control select2" id="filter_user" name="user_id">
                                        <option value="">{{ __('All Employees') }}</option>
                                        @foreach ($users as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_products" class="form-label">{{ __('Products') }}</label>
                                    <select class="form-control select2" id="filter_products" name="products" multiple>
                                        @foreach ($products as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_date_from" class="form-label">{{ __('Date From') }}</label>
                                    <input type="text" class="form-control" id="filter_date_from" name="date_from"
                                        placeholder="YYYY-MM-DD" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
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
