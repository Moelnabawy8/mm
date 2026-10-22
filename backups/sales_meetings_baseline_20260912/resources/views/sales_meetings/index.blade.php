@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Meetings') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Meetings') }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/summernote/summernote-bs4.css') }}">
@endpush
@push('script-page')
    <script src="{{ asset('css/summernote/summernote-bs4.js') }}"></script>
    <script>
        var SM_FILTER_KEY = 'crm_sales_meetings_filter';

        function smFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            active ? $btn.removeClass('btn-success').addClass('btn-warning') : $btn.removeClass('btn-warning').addClass('btn-success');
        }

        function hasSmActiveFilter(data) {
            return Object.values(data).some(function(v) { return v !== '' && v != null; });
        }

        $(document).ready(function() {
            if ($("#filter_date_from").length > 0) $("#filter_date_from").flatpickr({});
            if ($("#filter_date_to").length > 0) $("#filter_date_to").flatpickr({});

            var _saved = localStorage.getItem(SM_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.title) $('#filter_title').val(_sf.title);
                    if (_sf.lead_id) $('#filter_lead').val(_sf.lead_id).trigger('change');
                    if (_sf.deal_id) $('#filter_deal').val(_sf.deal_id).trigger('change');
                    if (_sf.meeting_type_id) $('#filter_type').val(_sf.meeting_type_id).trigger('change');
                    if (_sf.date_from) { var _fp1 = document.getElementById('filter_date_from')._flatpickr; if (_fp1) _fp1.setDate(_sf.date_from, false); }
                    if (_sf.date_to) { var _fp2 = document.getElementById('filter_date_to')._flatpickr; if (_fp2) _fp2.setDate(_sf.date_to, false); }
                    if (hasSmActiveFilter(_sf)) { smFilterBadge(true); window.currentSmFilterData = _sf; $('#apply-filter').trigger('click'); }
                } catch(e) { localStorage.removeItem(SM_FILTER_KEY); }
            }
        });

        $(document).on('click', '#apply-filter', function() {
            var data = {
                title: $('#filter_title').val(),
                lead_id: $('#filter_lead').val(),
                deal_id: $('#filter_deal').val(),
                meeting_type_id: $('#filter_type').val(),
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
            };
            localStorage.setItem(SM_FILTER_KEY, JSON.stringify(data));
            window.currentSmFilterData = data;
            smFilterBadge(hasSmActiveFilter(data));
            $.ajax({
                url: '{{ route('sales-meetings.index') }}',
                type: 'GET',
                data: data,
                success: function(response) { $('#meetings-table-container').html(response); }
            });
            $('#advancedFilterModal').modal('hide');
        });

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(SM_FILTER_KEY);
            window.currentSmFilterData = {};
            window.location.reload();
        });

        $(document).on('click', '#meetings-table-container .pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            $.ajax({
                url: url, type: 'GET', data: window.currentSmFilterData || {},
                success: function(response) {
                    $('#meetings-table-container').html(response);
                    $('html, body').animate({ scrollTop: $('#meetings-table-container').offset().top - 80 }, 200);
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    <div class="float-end">
        <a href="#" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#advancedFilterModal"
            title="{{ __('Advanced Filter') }}" data-bs-original-title="{{ __('Advanced Filter') }}">
            <i class="ti ti-filter"></i>
        </a>
        @can('create lead')
            <a href="#" data-url="{{ route('sales-meetings.create') }}" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Create Meeting') }}" data-bs-toggle="tooltip" title="{{ __('Create') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div id="meetings-table-container">
                        @include('sales_meetings._table')
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
                                    <label for="filter_title" class="form-label">{{ __('Title') }}</label>
                                    <input type="text" class="form-control" id="filter_title" name="title">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_lead" class="form-label">{{ __('Lead') }}</label>
                                    <select class="form-control select2" id="filter_lead" name="lead_id">
                                        <option value="">{{ __('Select Lead') }}</option>
                                        @foreach ($leads as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_deal" class="form-label">{{ __('Deal') }}</label>
                                    <select class="form-control select2" id="filter_deal" name="deal_id">
                                        <option value="">{{ __('Select Deal') }}</option>
                                        @foreach ($deals as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_type" class="form-label">{{ __('Meeting Type') }}</label>
                                    <select class="form-control select2" id="filter_type" name="meeting_type_id">
                                        <option value="">{{ __('Select Type') }}</option>
                                        @foreach ($types as $id => $name)
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
