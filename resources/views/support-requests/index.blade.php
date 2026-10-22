@extends('layouts.admin')
@section('page-title')
    {{ __('Team Support Requests') }}
@endsection

@section('title')
    {{ __('Team Support Requests') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Support Requests') }}</li>
@endsection

@push('script-page')
    <script>
        var SR_FILTER_KEY = 'crm_support_requests_filter';

        function srFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            active ? $btn.removeClass('btn-success').addClass('btn-warning') : $btn.removeClass('btn-warning').addClass('btn-success');
        }

        function hasSrActiveFilter(data) {
            return Object.values(data).some(function(v) { return v !== '' && v != null; });
        }

        $(document).ready(function() {
            if ($("#filter_date_from").length > 0) $("#filter_date_from").flatpickr({});
            if ($("#filter_date_to").length > 0) $("#filter_date_to").flatpickr({});

            var _saved = localStorage.getItem(SR_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.view_type) $('#filter_view_type').val(_sf.view_type);
                    if (_sf.status) $('#filter_status').val(_sf.status);
                    if (_sf.priority) $('#filter_priority').val(_sf.priority);
                    if (_sf.supporter_id) $('#filter_supporter').val(_sf.supporter_id).trigger('change');
                    if (_sf.lead_id) {
                        $('#filter_lead').val(_sf.lead_id).trigger('change');
                        // The picker's text box is not the field that is submitted,
                        // so the name has to be put back separately or the filter
                        // would be applied while the box looked empty.
                        if (_sf.lead_label) $('#filter_lead_search').val(_sf.lead_label);
                    }
                    if (_sf.date_from) { var _fp1 = document.getElementById('filter_date_from')._flatpickr; if (_fp1) _fp1.setDate(_sf.date_from, false); }
                    if (_sf.date_to) { var _fp2 = document.getElementById('filter_date_to')._flatpickr; if (_fp2) _fp2.setDate(_sf.date_to, false); }
                    if (hasSrActiveFilter(_sf)) { srFilterBadge(true); window.currentSrFilterData = _sf; $('#apply-filter').trigger('click'); }
                } catch(e) { localStorage.removeItem(SR_FILTER_KEY); }
            }
        });

        $(document).on('click', '#apply-filter', function() {
            var data = {
                view_type: $('#filter_view_type').val(),
                status: $('#filter_status').val(),
                priority: $('#filter_priority').val(),
                supporter_id: $('#filter_supporter').val(),
                lead_id: $('#filter_lead').val(),
                lead_label: $('#filter_lead_search').val(),
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
            };
            localStorage.setItem(SR_FILTER_KEY, JSON.stringify(data));
            window.currentSrFilterData = data;
            srFilterBadge(hasSrActiveFilter(data));
            $.ajax({
                url: '{{ route('support-requests.index') }}',
                type: 'GET',
                data: data,
                success: function(response) { $('#support-requests-table-container').html(response); }
            });
            $('#advancedFilterModal').modal('hide');
        });

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(SR_FILTER_KEY);
            window.currentSrFilterData = {};
            window.location.reload();
        });

        $(document).on('click', '#support-requests-table-container .pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            $.ajax({
                url: url, type: 'GET', data: window.currentSrFilterData || {},
                success: function(response) {
                    $('#support-requests-table-container').html(response);
                    $('html, body').animate({ scrollTop: $('#support-requests-table-container').offset().top - 80 }, 200);
                }
            });
        });

        $(document).on('change', '.status-quick-update', function() {
            var requestId = $(this).data('request-id');
            var status = $(this).val();

            $.ajax({
                url: '/support-requests/' + requestId + '/update-status',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        show_toastr('Success', response.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    show_toastr('Error', xhr.responseJSON.error || 'Something went wrong', 'error');
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
            <a href="{{ route('support-requests.create') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                title="{{ __('Request Support') }}">
                <i class="ti ti-plus"></i>
            </a>
        </div>
    @endcan
@endsection

@section('content')
    <div class="row mb-3">
        <div class="col-12">
            <div class="row">
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 bg-light-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-muted">{{ __('Requested by Me') }}</h6>
                                <i class="ti ti-help fs-4 text-primary"></i>
                            </div>
                            <h3 class="mb-1 text-primary">{{ $stats['total_requested'] }}</h3>
                            <small class="text-warning">
                                <i class="ti ti-clock-hour-4"></i> {{ $stats['pending_requested'] }} {{ __('Pending') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 bg-light-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-muted">{{ __('Assigned to Me') }}</h6>
                                <i class="ti ti-user-check fs-4 text-info"></i>
                            </div>
                            <h3 class="mb-1 text-info">{{ $stats['total_assigned'] }}</h3>
                            <small class="text-warning">
                                <i class="ti ti-clock-hour-4"></i> {{ $stats['pending_assigned'] }} {{ __('Pending') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 bg-light-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-muted">{{ __('In Progress') }}</h6>
                                <i class="ti ti-clock fs-4 text-warning"></i>
                            </div>
                            <h3 class="mb-1 text-warning">
                                {{ $stats['in_progress_requested'] + $stats['in_progress_assigned'] }}</h3>
                            <small class="text-muted">
                                {{ $stats['in_progress_requested'] }} {{ __('Req') }} /
                                {{ $stats['in_progress_assigned'] }} {{ __('Asg') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 bg-light-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-muted">{{ __('Completed') }}</h6>
                                <i class="ti ti-check fs-4 text-success"></i>
                            </div>
                            <h3 class="mb-1 text-success">
                                {{ $stats['completed_requested'] + $stats['completed_assigned'] }}</h3>
                            <small class="text-muted">
                                {{ $stats['completed_requested'] }} {{ __('Req') }} /
                                {{ $stats['completed_assigned'] }} {{ __('Asg') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 bg-light-secondary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-muted">{{ __('Support Value') }}</h6>
                                <i class="ti ti-currency-dollar fs-4 text-secondary"></i>
                            </div>
                            <h3 class="mb-1 text-secondary">
                                @php
                                    $totalValue = \App\Models\SupportRequest::where(function ($q) {
                                        $q->where('requester_id', Auth::user()->id)->orWhere(
                                            'supporter_id',
                                            Auth::user()->id,
                                        );
                                    })
                                        ->where('client_subscribed', true)
                                        ->sum('subscription_amount');
                                @endphp
                                {{ number_format($totalValue, 0) }}
                            </h3>
                            <small class="text-muted">
                                <i class="ti ti-trending-up"></i> {{ __('Total Revenue') }}
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                    <div class="card shadow-sm border-0 h-100 bg-light-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0 text-muted">{{ __('Subscribed') }}</h6>
                                <i class="ti ti-user-dollar fs-4 text-dark"></i>
                            </div>
                            <h3 class="mb-1 text-dark">
                                @php
                                    $subscribedCount = \App\Models\SupportRequest::where(function ($q) {
                                        $q->where('requester_id', Auth::user()->id)->orWhere(
                                            'supporter_id',
                                            Auth::user()->id,
                                        );
                                    })
                                        ->where('client_subscribed', true)
                                        ->count();
                                @endphp
                                {{ $subscribedCount }}
                            </h3>
                            <small class="text-success">
                                <i class="ti ti-check"></i> {{ __('Clients') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div id="support-requests-table-container">
                @include('support-requests._table')
            </div>
        </div>
    </div>

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
                                    <label for="filter_view_type" class="form-label">{{ __('View Type') }}</label>
                                    <select class="form-control select2" id="filter_view_type" name="view_type">
                                        <option value="">{{ __('All') }}</option>
                                        <option value="requested">{{ __('Requested by Me') }}</option>
                                        <option value="assigned">{{ __('Assigned to Me') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_status" class="form-label">{{ __('Status') }}</label>
                                    <select class="form-control select2" id="filter_status" name="status">
                                        <option value="">{{ __('All Statuses') }}</option>
                                        <option value="pending">{{ __('Pending') }}</option>
                                        <option value="in_progress">{{ __('In Progress') }}</option>
                                        <option value="completed">{{ __('Completed') }}</option>
                                        <option value="cancelled">{{ __('Cancelled') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_priority" class="form-label">{{ __('Priority') }}</label>
                                    <select class="form-control select2" id="filter_priority" name="priority">
                                        <option value="">{{ __('All Priorities') }}</option>
                                        <option value="low">{{ __('Low') }}</option>
                                        <option value="normal">{{ __('Normal') }}</option>
                                        <option value="high">{{ __('High') }}</option>
                                        <option value="urgent">{{ __('Urgent') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_supporter" class="form-label">{{ __('Supporter') }}</label>
                                    <select class="form-control select2" id="filter_supporter" name="supporter_id">
                                        <option value="">{{ __('All Supporters') }}</option>
                                        @foreach ($teammates as $teammate)
                                            <option value="{{ $teammate->id }}">{{ $teammate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_lead" class="form-label">{{ __('Lead/Client') }}</label>
                                    {{-- The hidden field keeps the id #filter_lead, so the filter
                                         javascript that reads $('#filter_lead').val() is untouched. --}}
                                    @include('deals._lead_picker', [
                                        'pickerId' => 'filter_lead',
                                        'fieldName' => 'lead_id',
                                        'selectedId' => null,
                                        'selectedName' => '',
                                        'required' => false,
                                        'searchUrl' => route('support-requests.leads.search'),
                                    ])
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
