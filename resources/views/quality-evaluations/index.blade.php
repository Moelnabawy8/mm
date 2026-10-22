@extends('layouts.admin')
@section('page-title')
    {{ __('Quality Evaluations') }}
@endsection

@section('title')
    {{ __('Quality Evaluations') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Quality Evaluations') }}</li>
@endsection

@push('script-page')
    <script>
        function switchView(view) {
            $('.view-toggle').removeClass('active btn-primary').addClass('btn-outline-primary');
            $('.view-toggle[data-view="' + view + '"]').removeClass('btn-outline-primary').addClass(
                'active btn-primary');

            if (view === 'grid') {
                $('#table-view').hide();
                $('#grid-view').show();
            } else {
                $('#grid-view').hide();
                $('#table-view').show();
            }
        }

        $(document).on('click', '.view-toggle', function() {
            var view = $(this).data('view');
            switchView(view);
            localStorage.setItem('quality_evaluations_view', view);
        });

        $(document).ready(function() {
            flatpickr('#filter_date', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
            });
            flatpickr('#filter_updated_date', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
            });

            // View Toggle Handler
            var savedView = localStorage.getItem('quality_evaluations_view') || 'table';
            switchView(savedView);

            // Restore saved filter on page load
            var _saved = localStorage.getItem(QE_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.evaluator_id) $('#filter_evaluator').val(_sf.evaluator_id);
                    if (_sf.is_potential_lead !== '' && _sf.is_potential_lead != null) $('#filter_potential').val(_sf.is_potential_lead);
                    if (_sf.engagement_level) $('#filter_engagement').val(_sf.engagement_level);
                    if (_sf.course_fit_level) $('#filter_course_fit').val(_sf.course_fit_level);
                    if (_sf.rating) $('#filter_rating').val(_sf.rating);
                    if (_sf.career_stage) $('#filter_career_stage').val(_sf.career_stage);
                    if (_sf.city) $('#filter_city').val(_sf.city);
                    if (_sf.campaign_name) $('#filter_campaign').val(_sf.campaign_name).trigger('change');

                    // Lead filters
                    if (_sf.pipeline) $('#filter_pipeline').val(_sf.pipeline).trigger('change');
                    if (_sf.stage) $('#filter_stage').val(_sf.stage).trigger('change');
                    if (_sf.name) $('#filter_name').val(_sf.name);
                    if (_sf.phone) $('#filter_phone').val(_sf.phone);
                    if (_sf.subject) $('#filter_subject').val(_sf.subject);
                    if (_sf.country) $('#filter_country').val(_sf.country).trigger('change');
                    if (_sf.costing_type) $('#filter_costing_type').val(_sf.costing_type);
                    if (_sf.gender) $('#filter_gender').val(_sf.gender);
                    if (_sf.min_cost) $('#filter_min_cost').val(_sf.min_cost);
                    if (_sf.max_cost) $('#filter_max_cost').val(_sf.max_cost);
                    if (_sf.users) $('#filter_users').val(_sf.users.split(',')).trigger('change');
                    if (_sf.products) $('#filter_products').val(_sf.products.split(',')).trigger('change');
                    if (_sf.sources) $('#filter_sources').val(_sf.sources.split(',')).trigger('change');
                    if (_sf.labels) $('#filter_labels').val(_sf.labels.split(',')).trigger('change');
                    if (_sf.interests) $('#filter_interests').val(_sf.interests.split(',')).trigger('change');
                    if (_sf.sales_group) $('#filter_sales_group').val(_sf.sales_group);
                    if (_sf.unassigned == 1) $('#filter_unassigned').prop('checked', true);
                    if (_sf.no_activity == 1) $('#filter_no_activity').prop('checked', true);

                    if (_sf.date) {
                        var _fp = document.getElementById('filter_date')._flatpickr;
                        if (_fp) _fp.setDate(_sf.date.includes(' to ') ? _sf.date.split(' to ') : [_sf.date], false);
                        else $('#filter_date').val(_sf.date);
                    }
                    if (_sf.updated_date) {
                        var _fp2 = document.getElementById('filter_updated_date')._flatpickr;
                        if (_fp2) _fp2.setDate(_sf.updated_date.includes(' to ') ? _sf.updated_date.split(' to ') : [_sf.updated_date], false);
                        else $('#filter_updated_date').val(_sf.updated_date);
                    }
                    var _savedPage = localStorage.getItem(QE_PAGE_KEY);
                    if (hasQEActiveFilter(_sf) || _savedPage) {
                        if (hasQEActiveFilter(_sf)) qeFilterBadge(true);
                        var _url = '{{ route('quality-evaluations.index') }}';
                        if (_savedPage && _savedPage > 1) _url += '?page=' + _savedPage;
                        $.ajax({
                            url: _url,
                            type: 'GET',
                            data: _sf,
                            success: function(response) {
                                $('#quality-evaluations-main-container').html(response);
                            }
                        });
                    }
                } catch(e) {
                    localStorage.removeItem(QE_FILTER_KEY);
                }
            } else {
                var _savedPage = localStorage.getItem(QE_PAGE_KEY);
                if (_savedPage && _savedPage > 1) {
                    $.ajax({
                        url: '{{ route('quality-evaluations.index') }}?page=' + _savedPage,
                        type: 'GET',
                        success: function(response) {
                            $('#quality-evaluations-main-container').html(response);
                        }
                    });
                }
            }
        });

        var QE_FILTER_KEY = 'crm_quality_evaluations_filter';
        var QE_PAGE_KEY = 'crm_quality_evaluations_page';

        function qeFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            if (active) {
                $btn.removeClass('btn-success').addClass('btn-warning');
            } else {
                $btn.removeClass('btn-warning').addClass('btn-success');
            }
        }

        function hasQEActiveFilter(data) {
            return Object.entries(data).some(function(e) {
                var k = e[0], v = e[1];
                if (k === 'unassigned' || k === 'no_activity') return v == 1;
                return v !== '' && v != null && v !== 0;
            });
        }

        function applyFilter(url = '{{ route('quality-evaluations.index') }}') {
            var data = {
                evaluator_id: $('#filter_evaluator').val(),
                is_potential_lead: $('#filter_potential').val(),
                engagement_level: $('#filter_engagement').val(),
                course_fit_level: $('#filter_course_fit').val(),
                rating: $('#filter_rating').val(),
                career_stage: $('#filter_career_stage').val(),
                city: $('#filter_city').val(),
                date: $('#filter_date').val(),
                campaign_name: $('#filter_campaign').val(),

                // Lead filters
                pipeline: $('#filter_pipeline').val(),
                stage: $('#filter_stage').val(),
                name: $('#filter_name').val(),
                phone: $('#filter_phone').val(),
                subject: $('#filter_subject').val(),
                country: $('#filter_country').val(),
                costing_type: $('#filter_costing_type').val(),
                gender: $('#filter_gender').val(),
                min_cost: $('#filter_min_cost').val(),
                max_cost: $('#filter_max_cost').val(),
                users: $('#filter_users').val() ? $('#filter_users').val().join(',') : '',
                products: $('#filter_products').val() ? $('#filter_products').val().join(',') : '',
                sources: $('#filter_sources').val() ? $('#filter_sources').val().join(',') : '',
                labels: $('#filter_labels').val() ? $('#filter_labels').val().join(',') : '',
                interests: $('#filter_interests').val() ? $('#filter_interests').val().join(',') : '',
                sales_group: $('#filter_sales_group').val(),
                updated_date: $('#filter_updated_date').val(),
                unassigned: $('#filter_unassigned').is(':checked') ? 1 : 0,
                no_activity: $('#filter_no_activity').is(':checked') ? 1 : 0,
            };

            localStorage.setItem(QE_FILTER_KEY, JSON.stringify(data));
            qeFilterBadge(hasQEActiveFilter(data));

            // Track page number from pagination URL; reset if this is a fresh filter apply
            var pageMatch = url.match(/[?&]page=(\d+)/);
            if (pageMatch) {
                localStorage.setItem(QE_PAGE_KEY, pageMatch[1]);
            } else {
                localStorage.removeItem(QE_PAGE_KEY);
            }

            $.ajax({
                url: url,
                type: 'GET',
                data: data,
                success: function(response) {
                    $('#quality-evaluations-main-container').html(response);
                }
            });
            $('#advancedFilterModal').modal('hide');
        }

        $(document).on('click', '#apply-filter', function() {
            applyFilter();
        });

        // Intercept pagination clicks — use saved filter from localStorage directly
        // (don't re-read form inputs, they may not be in sync with select2/flatpickr)
        $(document).on('click', '.pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            var saved = localStorage.getItem(QE_FILTER_KEY);
            var filterData = {};
            if (saved) {
                try { filterData = JSON.parse(saved); } catch(e) { filterData = {}; }
            }

            var pageMatch = url.match(/[?&]page=(\d+)/);
            if (pageMatch) localStorage.setItem(QE_PAGE_KEY, pageMatch[1]);

            $.ajax({
                url: url,
                type: 'GET',
                data: filterData,
                success: function(response) {
                    $('#quality-evaluations-main-container').html(response);
                }
            });
        });

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(QE_FILTER_KEY);
            localStorage.removeItem(QE_PAGE_KEY);
            window.location.reload();
        });

        $(document).on('click', '#export-btn', function(e) {
            e.preventDefault();
            var params = new URLSearchParams({
                evaluator_id: $('#filter_evaluator').val() || '',
                is_potential_lead: $('#filter_potential').val() || '',
                engagement_level: $('#filter_engagement').val() || '',
                course_fit_level: $('#filter_course_fit').val() || '',
                rating: $('#filter_rating').val() || '',
                career_stage: $('#filter_career_stage').val() || '',
                city: $('#filter_city').val() || '',
                date: $('#filter_date').val() || '',
                campaign_name: $('#filter_campaign').val() || '',

                pipeline: $('#filter_pipeline').val() || '',
                stage: $('#filter_stage').val() || '',
                name: $('#filter_name').val() || '',
                phone: $('#filter_phone').val() || '',
                subject: $('#filter_subject').val() || '',
                country: $('#filter_country').val() || '',
                costing_type: $('#filter_costing_type').val() || '',
                gender: $('#filter_gender').val() || '',
                min_cost: $('#filter_min_cost').val() || '',
                max_cost: $('#filter_max_cost').val() || '',
                users: $('#filter_users').val() ? $('#filter_users').val().join(',') : '',
                products: $('#filter_products').val() ? $('#filter_products').val().join(',') : '',
                sources: $('#filter_sources').val() ? $('#filter_sources').val().join(',') : '',
                labels: $('#filter_labels').val() ? $('#filter_labels').val().join(',') : '',
                interests: $('#filter_interests').val() ? $('#filter_interests').val().join(',') : '',
                sales_group: $('#filter_sales_group').val() || '',
                updated_date: $('#filter_updated_date').val() || '',
                unassigned: $('#filter_unassigned').is(':checked') ? 1 : 0,
                no_activity: $('#filter_no_activity').is(':checked') ? 1 : 0,
            });
            // Remove empty params
            for (var key of [...params.keys()]) {
                if (!params.get(key) || params.get(key) === '0') params.delete(key);
            }
            window.location.href = '{{ route('quality-evaluations.export') }}?' + params.toString();
        });

        // ==================== Users Activity Report ====================
        $(document).ready(function() {
            if (!$('#usersActivityModal').length) return;

            flatpickr('#ua_date_range', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
                maxDate: 'today',
            });

            // Quick preset buttons
            $(document).on('click', '.ua-preset', function() {
                var fp = document.getElementById('ua_date_range')._flatpickr;
                if (!fp) return;
                var days = $(this).data('days');
                var preset = $(this).data('preset');
                var end = new Date();
                var start;

                if (preset === 'this_month') {
                    start = new Date(end.getFullYear(), end.getMonth(), 1);
                } else if (preset === 'last_month') {
                    start = new Date(end.getFullYear(), end.getMonth() - 1, 1);
                    end = new Date(end.getFullYear(), end.getMonth(), 0);
                } else {
                    start = new Date();
                    start.setDate(start.getDate() - parseInt(days, 10));
                }
                fp.setDate([start, end], true);
            });

            function statusBadge(s) {
                if (s === 'inactive') return '<span class="badge bg-danger"><i class="ti ti-alert-circle me-1"></i>{{ __('Inactive') }}</span>';
                if (s === 'low') return '<span class="badge bg-warning"><i class="ti ti-alert-triangle me-1"></i>{{ __('Low') }}</span>';
                return '<span class="badge bg-success"><i class="ti ti-circle-check me-1"></i>{{ __('Active') }}</span>';
            }

            function renderRows(rows) {
                if (!rows.length) {
                    return '<tr><td colspan="7" class="text-center text-muted py-3">{{ __('No users match the current filters.') }}</td></tr>';
                }
                var html = '';
                rows.forEach(function(r) {
                    var rowClass = r.status === 'inactive' ? 'table-danger' : (r.status === 'low' ? 'table-warning' : '');
                    html += '<tr class="' + rowClass + '">';
                    html += '<td><strong>' + $('<div>').text(r.name).html() + '</strong>';
                    if (r.email) html += '<br><small class="text-muted">' + $('<div>').text(r.email).html() + '</small>';
                    html += '</td>';
                    html += '<td><span class="badge bg-light-secondary">' + $('<div>').text(r.type || '-').html() + '</span></td>';
                    html += '<td>' + $('<div>').text(r.sales_group || '-').html() + '</td>';
                    html += '<td class="text-center">' + r.assigned_leads + '</td>';
                    html += '<td class="text-center"><strong>' + r.count + '</strong></td>';
                    if (r.last_activity) {
                        html += '<td><span title="' + r.last_activity + '">' + r.last_human + '</span></td>';
                    } else {
                        html += '<td><span class="text-muted">{{ __('Never') }}</span></td>';
                    }
                    html += '<td>' + statusBadge(r.status) + '</td>';
                    html += '</tr>';
                });
                return html;
            }

            var lastReportRows = [];

            $(document).on('click', '#ua_run_btn', function() {
                var dateRange = $('#ua_date_range').val();
                var threshold = $('#ua_threshold').val() || 0;
                var status = $('#ua_status_filter').val();

                $('#ua_empty').hide();
                $('#ua_results_wrapper').hide();
                $('#ua_summary').hide();
                $('#ua_export_btn').hide();
                $('#ua_loading').show();

                $.ajax({
                    url: '{{ route('quality-evaluations.users-activity') }}',
                    type: 'GET',
                    data: { date_range: dateRange, threshold: threshold, status: status },
                    success: function(resp) {
                        $('#ua_loading').hide();
                        $('#ua_total_users').text(resp.totals.users);
                        $('#ua_total_inactive').text(resp.totals.inactive);
                        $('#ua_total_low').text(resp.totals.low);
                        $('#ua_total_active').text(resp.totals.active);
                        $('#ua_summary').show();
                        $('#ua_results_body').html(renderRows(resp.rows));
                        $('#ua_results_wrapper').show();
                        lastReportRows = resp.rows;
                        if (lastReportRows.length) $('#ua_export_btn').show();
                    },
                    error: function(xhr) {
                        $('#ua_loading').hide();
                        $('#ua_empty').show();
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : '{{ __('Failed to load report.') }}';
                        alert(msg);
                    }
                });
            });

            $(document).on('click', '#ua_export_btn', function() {
                if (!lastReportRows.length) return;
                var headers = ['User', 'Email', 'Type', 'Sales Group', 'Assigned Leads', 'Evaluations in Range', 'Last Activity', 'Status'];
                var lines = [headers.join(',')];
                lastReportRows.forEach(function(r) {
                    var cols = [
                        r.name, r.email || '', r.type || '', r.sales_group || '',
                        r.assigned_leads, r.count, r.last_activity || 'Never', r.status
                    ];
                    lines.push(cols.map(function(c) {
                        c = String(c == null ? '' : c);
                        if (c.indexOf(',') !== -1 || c.indexOf('"') !== -1 || c.indexOf('\n') !== -1) {
                            c = '"' + c.replace(/"/g, '""') + '"';
                        }
                        return c;
                    }).join(','));
                });
                var csv = '﻿' + lines.join('\n');
                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'users_activity_' + (new Date().toISOString().slice(0, 10)) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            // Reset state when modal opens
            $('#usersActivityModal').on('show.bs.modal', function() {
                $('#ua_loading').hide();
                if (!lastReportRows.length) {
                    $('#ua_empty').show();
                    $('#ua_results_wrapper').hide();
                    $('#ua_summary').hide();
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    <div class="col-12 text-end btn btn-sm">
        <div class="btn-group me-1" role="group">
            <button type="button" class="btn btn-sm btn-outline-primary view-toggle" data-view="grid">
                <i class="ti ti-layout-grid"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary view-toggle active" data-view="table">
                <i class="ti ti-table"></i>
            </button>
        </div>
        <a href="#" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#advancedFilterModal"
            title="{{ __('Advanced Filter') }}" data-bs-original-title="{{ __('Advanced Filter') }}">
            <i class="ti ti-filter"></i>
        </a>
        @if (!empty($isPrivileged) && $isPrivileged)
            <a href="#" class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#usersActivityModal"
                title="{{ __('Users Activity Report') }}" data-bs-original-title="{{ __('Users Activity Report') }}">
                <i class="ti ti-users"></i> {{ __('Users Activity') }}
            </a>
        @endif
        <a href="#" id="export-btn" class="btn btn-sm btn-outline-success me-1" title="{{ __('Export CSV') }}">
            <i class="ti ti-file-spreadsheet"></i> {{ __('Export') }}
        </a>
    </div>
@endsection

@section('content')
    <div id="quality-evaluations-main-container">
        @include('quality-evaluations._main_content')
    </div>


    <div class="modal fade" id="advancedFilterModal" tabindex="-1" aria-labelledby="advancedFilterModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="advancedFilterModalLabel">{{ __('Advanced Filter') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="filter_evaluator" class="form-label">{{ __('Evaluator') }}</label>
                            <select class="form-control" id="filter_evaluator" name="evaluator_id">
                                <option value="">{{ __('All Evaluators') }}</option>
                                @foreach ($evaluators as $evaluator)
                                    <option value="{{ $evaluator->id }}">{{ $evaluator->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_campaign" class="form-label">{{ __('Campaign Name') }}</label>
                            <select class="form-control select2" id="filter_campaign" name="campaign_name">
                                <option value="">{{ __('All Campaigns') }}</option>
                                @foreach ($campaigns as $campaign)
                                    <option value="{{ $campaign }}" {{ request('campaign_name') == $campaign ? 'selected' : '' }}>{{ $campaign }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_potential" class="form-label">{{ __('Potential Lead') }}</label>
                            <select class="form-control" id="filter_potential" name="is_potential_lead">
                                <option value="">{{ __('All') }}</option>
                                <option value="yes">{{ __('Yes') }}</option>
                                <option value="no">{{ __('No') }}</option>
                                <option value="unknown">{{ __('Unknown') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_engagement" class="form-label">{{ __('Engagement Level') }}</label>
                            <select class="form-control" id="filter_engagement" name="engagement_level">
                                <option value="">{{ __('All') }}</option>
                                <option value="highly_engaged">{{ __('Highly Engaged') }}</option>
                                <option value="moderate">{{ __('Moderate') }}</option>
                                <option value="low_engagement">{{ __('Low Engagement') }}</option>
                                <option value="not_engaged">{{ __('Not Engaged') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_course_fit" class="form-label">{{ __('Course Fit Level') }}</label>
                            <select class="form-control" id="filter_course_fit" name="course_fit_level">
                                <option value="">{{ __('All') }}</option>
                                <option value="perfect_fit">{{ __('Perfect Fit') }}</option>
                                <option value="good_fit">{{ __('Good Fit') }}</option>
                                <option value="partial_fit">{{ __('Partial Fit') }}</option>
                                <option value="not_a_fit">{{ __('Not a Fit') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_rating" class="form-label">{{ __('Rating') }}</label>
                            <select class="form-control" id="filter_rating" name="rating">
                                <option value="">{{ __('All Ratings') }}</option>
                                <option value="5">5 {{ __('Stars') }}</option>
                                <option value="4">4 {{ __('Stars') }}</option>
                                <option value="3">3 {{ __('Stars') }}</option>
                                <option value="2">2 {{ __('Stars') }}</option>
                                <option value="1">1 {{ __('Star') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_career_stage" class="form-label">{{ __('Career Stage') }}</label>
                            <select class="form-control" id="filter_career_stage" name="career_stage">
                                <option value="">{{ __('All') }}</option>
                                <option value="student">{{ __('Student') }}</option>
                                <option value="graduate_employed_same_field">{{ __('Graduate - Employed in Same Field') }}
                                </option>
                                <option value="graduate_employed_different_field">
                                    {{ __('Graduate - Employed in Different Field') }}</option>
                                <option value="graduate_unemployed">{{ __('Graduate - Unemployed') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_city" class="form-label">{{ __('City') }}</label>
                            <input type="text" class="form-control" id="filter_city" name="city"
                                placeholder="{{ __('Enter city name') }}">
                        </div>

                        {{-- Lead-based filters (mirrors /leads) --}}
                        <div class="col-12"><hr><h6 class="text-muted">{{ __('Lead Filters') }}</h6></div>

                        <div class="col-md-6 mb-3">
                            <label for="filter_pipeline" class="form-label">{{ __('Pipeline') }}</label>
                            <select class="form-control select2" id="filter_pipeline" name="pipeline">
                                <option value="">{{ __('Select Pipeline') }}</option>
                                @foreach ($pipelines as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_stage" class="form-label">{{ __('Stage') }}</label>
                            <select class="form-control select2" id="filter_stage" name="stage">
                                <option value="">{{ __('Select Stage') }}</option>
                                @foreach ($stages as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_name" class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" id="filter_name" name="name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_phone" class="form-label">{{ __('Phone') }}</label>
                            <input type="text" class="form-control" id="filter_phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_subject" class="form-label">{{ __('Subject') }}</label>
                            <input type="text" class="form-control" id="filter_subject" name="subject">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_country" class="form-label">{{ __('Country') }}</label>
                            <select class="form-control select2" id="filter_country" name="country">
                                <option value="">{{ __('Select Country') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country }}">{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_costing_type" class="form-label">{{ __('Costing Type') }}</label>
                            <select class="form-control" id="filter_costing_type" name="costing_type">
                                <option value="">{{ __('Select Costing Type') }}</option>
                                <option value="organic">{{ __('Organic') }}</option>
                                <option value="paid">{{ __('Paid') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_gender" class="form-label">{{ __('Gender') }}</label>
                            <select class="form-control" id="filter_gender" name="gender">
                                <option value="">{{ __('Select Gender') }}</option>
                                <option value="Male">{{ __('Male') }}</option>
                                <option value="Female">{{ __('Female') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="filter_min_cost" class="form-label">{{ __('Min Cost') }}</label>
                            <input type="number" class="form-control" id="filter_min_cost" name="min_cost" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="filter_max_cost" class="form-label">{{ __('Max Cost') }}</label>
                            <input type="number" class="form-control" id="filter_max_cost" name="max_cost" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_users" class="form-label">{{ __('Users') }}</label>
                            <select class="form-control select2" id="filter_users" name="users" multiple>
                                @foreach ($users as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_products" class="form-label">{{ __('Products') }}</label>
                            <select class="form-control select2" id="filter_products" name="products" multiple>
                                @foreach ($products as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_sources" class="form-label">{{ __('Sources') }}</label>
                            <select class="form-control select2" id="filter_sources" name="sources" multiple>
                                @foreach ($sources as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_labels" class="form-label">{{ __('Labels') }}</label>
                            <select class="form-control select2" id="filter_labels" name="labels" multiple>
                                @foreach ($labels as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_interests" class="form-label">{{ __('Interests') }}</label>
                            <select class="form-control select2" id="filter_interests" name="interests" multiple>
                                @foreach ($interests as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="filter_sales_group" class="form-label">{{ __('Sales Group') }}</label>
                            <select class="form-control" id="filter_sales_group" name="sales_group">
                                <option value="">{{ __('All Groups') }}</option>
                                <option value="A">{{ __('Group A') }}</option>
                                <option value="B">{{ __('Group B') }}</option>
                                <option value="JOR">{{ __('Group JOR') }}</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="filter_date" class="form-label">
                                <i class="ti ti-calendar me-1"></i>{{ __('Created Date') }}
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                <input type="text" class="form-control" id="filter_date" name="date"
                                    placeholder="{{ __('Select date range...') }}" autocomplete="off" readonly>
                            </div>
                            <small class="text-muted">{{ __('Click to pick a date range') }}</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="filter_updated_date" class="form-label">
                                <i class="ti ti-clock-edit me-1"></i>{{ __('Last Updated Date') }}
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                <input type="text" class="form-control" id="filter_updated_date" name="updated_date"
                                    placeholder="{{ __('Select date range...') }}" autocomplete="off" readonly>
                            </div>
                            <small class="text-muted">{{ __('Filter leads by when they were last modified') }}</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="filter_unassigned" name="unassigned" value="1">
                                <label class="form-check-label" for="filter_unassigned">
                                    <i class="ti ti-user-off me-1"></i>{{ __('Unassigned Leads Only') }}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="filter_no_activity" name="no_activity" value="1">
                                <label class="form-check-label" for="filter_no_activity">
                                    <i class="ti ti-alert-triangle me-1"></i>{{ __('No Activity') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="clear-filter">{{ __('Clear') }}</button>
                    <button type="button" class="btn btn-primary" id="apply-filter">{{ __('Apply Filter') }}</button>
                </div>
            </div>
        </div>
    </div>

    @if (!empty($isPrivileged) && $isPrivileged)
    <div class="modal fade" id="usersActivityModal" tabindex="-1" aria-labelledby="usersActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="usersActivityModalLabel">
                        <i class="ti ti-users me-1"></i>{{ __('Users Activity Report') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        <i class="ti ti-info-circle me-1"></i>
                        {{ __('Identify users who have not submitted quality evaluations in a chosen period. Auto-created "Unknown" placeholders are excluded so the count reflects real evaluation work.') }}
                    </p>

                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3">
                            <label for="ua_date_range" class="form-label">
                                <i class="ti ti-calendar me-1"></i>{{ __('Date Range') }}
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                <input type="text" class="form-control" id="ua_date_range" placeholder="{{ __('Pick a date range...') }}" autocomplete="off" readonly>
                            </div>
                            <small class="text-muted">{{ __('Defaults to the last 30 days if empty.') }}</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="ua_threshold" class="form-label">
                                <i class="ti ti-target me-1"></i>{{ __('Low-activity Threshold') }}
                            </label>
                            <input type="number" min="0" class="form-control" id="ua_threshold" value="0" placeholder="0">
                            <small class="text-muted">{{ __('Users below this count are flagged as Low.') }}</small>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="ua_status_filter" class="form-label">
                                <i class="ti ti-filter me-1"></i>{{ __('Show') }}
                            </label>
                            <select class="form-control" id="ua_status_filter">
                                <option value="">{{ __('All') }}</option>
                                <option value="inactive">{{ __('Inactive only') }}</option>
                                <option value="low">{{ __('Low only') }}</option>
                                <option value="active">{{ __('Active only') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 d-grid">
                            <button type="button" class="btn btn-primary" id="ua_run_btn">
                                <i class="ti ti-search me-1"></i>{{ __('Run Report') }}
                            </button>
                        </div>
                    </div>

                    {{-- Quick presets --}}
                    <div class="mb-3">
                        <small class="text-muted me-2">{{ __('Quick presets:') }}</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 ua-preset" data-days="7">{{ __('Last 7 days') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 ua-preset" data-days="14">{{ __('Last 14 days') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 ua-preset" data-days="30">{{ __('Last 30 days') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 ua-preset" data-days="60">{{ __('Last 60 days') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 ua-preset" data-preset="this_month">{{ __('This month') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1 ua-preset" data-preset="last_month">{{ __('Last month') }}</button>
                    </div>

                    {{-- Summary cards --}}
                    <div class="row mb-3" id="ua_summary" style="display:none;">
                        <div class="col-md-3">
                            <div class="card border-secondary"><div class="card-body py-2 text-center">
                                <small class="text-muted">{{ __('Total Users') }}</small>
                                <h4 class="mb-0" id="ua_total_users">0</h4>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger"><div class="card-body py-2 text-center">
                                <small class="text-danger">{{ __('Inactive') }}</small>
                                <h4 class="mb-0 text-danger" id="ua_total_inactive">0</h4>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning"><div class="card-body py-2 text-center">
                                <small class="text-warning">{{ __('Low Activity') }}</small>
                                <h4 class="mb-0 text-warning" id="ua_total_low">0</h4>
                            </div></div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-success"><div class="card-body py-2 text-center">
                                <small class="text-success">{{ __('Active') }}</small>
                                <h4 class="mb-0 text-success" id="ua_total_active">0</h4>
                            </div></div>
                        </div>
                    </div>

                    <div id="ua_loading" style="display:none;" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2 mb-0">{{ __('Loading report...') }}</p>
                    </div>

                    <div id="ua_empty" class="text-center py-4 text-muted">
                        <i class="ti ti-clipboard-list" style="font-size:48px;"></i>
                        <p class="mt-2 mb-0">{{ __('Pick a date range and run the report to see results.') }}</p>
                    </div>

                    <div class="table-responsive" id="ua_results_wrapper" style="display:none;">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('User') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Sales Group') }}</th>
                                    <th class="text-center">{{ __('Assigned Leads') }}</th>
                                    <th class="text-center">{{ __('Evaluations in Range') }}</th>
                                    <th>{{ __('Last Activity') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody id="ua_results_body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-success" id="ua_export_btn" style="display:none;">
                        <i class="ti ti-file-spreadsheet me-1"></i>{{ __('Export CSV') }}
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
