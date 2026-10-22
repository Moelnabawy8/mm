@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Leads') }} @if ($pipeline)
        - {{ $pipeline->name }}
    @endif
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/summernote/summernote-bs4.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dragula.min.css') }}" id="main-style-link">
    <style>
        .flatpickr-calendar { z-index: 99999 !important; }
    </style>
@endpush
@push('script-page')
    <script src="{{ asset('css/summernote/summernote-bs4.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dragula.min.js') }}"></script>
    <script>
        ! function(a) {
            "use strict";
            var t = function() {
                this.$body = a("body")
            };
            t.prototype.init = function() {
                a('[data-plugin="dragula"]').each(function() {
                    var t = a(this).data("containers"),
                        n = [];
                    if (t)
                        for (var i = 0; i < t.length; i++) n.push(a("#" + t[i])[0]);
                    else n = [a(this)[0]];
                    var r = a(this).data("handleclass");
                    r ? dragula(n, {
                        moves: function(a, t, n) {
                            return n.classList.contains(r)
                        }
                    }) : dragula(n).on('drop', function(el, target, source, sibling) {

                        var order = [];
                        $("#" + target.id + " > div").each(function() {
                            order[$(this).index()] = $(this).attr('data-id');
                        });

                        var id = $(el).attr('data-id');

                        var old_status = $("#" + source.id).data('status');
                        var new_status = $("#" + target.id).data('status');
                        var stage_id = $(target).attr('data-id');
                        var pipeline_id = '{{ $pipeline->id }}';

                        $("#" + source.id).parent().find('.count').text($("#" + source.id + " > div")
                            .length);
                        $("#" + target.id).parent().find('.count').text($("#" + target.id + " > div")
                            .length);
                        $.ajax({
                            url: '{{ route('leads.order') }}',
                            type: 'POST',
                            data: {
                                lead_id: id,
                                stage_id: stage_id,
                                order: order,
                                new_status: new_status,
                                old_status: old_status,
                                pipeline_id: pipeline_id,
                                "_token": $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(data) {
                                show_toastr(data.status, data.message, data.status);
                            },
                            error: function(data) {
                                data = data.responseJSON;
                                show_toastr(data.status, data.message, data.status)
                            }
                        });
                    });
                })
            }, a.Dragula = new t, a.Dragula.Constructor = t
        }(window.jQuery),
        function(a) {
            "use strict";

            a.Dragula.init()

        }(window.jQuery);
    </script>
    <script>
        $(document).on("change", "#default_pipeline_id", function() {
            $('#change-pipeline').submit();
        });
    </script>
    <script>
        // Bulk Select Functionality for Kanban View
        // Bulk actions helpers — defined outside ready() so delegated handlers can access them
        function updateBulkActions() {
            var selectedCount = $('.lead-select-checkbox:checked').length;
            $('#selected-leads-count').text(selectedCount);
            if (selectedCount > 0) {
                $('#bulk-actions-bar').slideDown(200);
            } else {
                $('#bulk-actions-bar').slideUp(200);
            }
        }

        // Delegated handlers survive AJAX kanban reloads
        $(document).on('change', '.lead-select-checkbox', function() {
            updateBulkActions();
            var total   = $('.lead-select-checkbox').length;
            var checked = $('.lead-select-checkbox:checked').length;
            $('#select-all-leads-kanban').prop('checked', total > 0 && total === checked);
            $('#select-all-leads-kanban').prop('indeterminate', checked > 0 && checked < total);
        });

        $(document).on('change', '#select-all-leads-kanban', function() {
            var isChecked = $(this).is(':checked');
            $('.lead-select-checkbox').prop('checked', isChecked);
            updateBulkActions();
        });

        $(document).ready(function() {

            // Clear selection
            $(document).on('click', '#clear-selection-btn', function() {
                $('.lead-select-checkbox').prop('checked', false);
                $('#select-all-leads-kanban').prop('checked', false).prop('indeterminate', false);
                updateBulkActions();
            });

            // Bulk delete
            $(document).on('click', '#bulk-delete-leads-btn', function() {
                var selectedIds = [];
                var selectedNames = [];

                $('.lead-select-checkbox:checked').each(function() {
                    selectedIds.push($(this).val());
                    selectedNames.push($(this).data('lead-name'));
                });

                if (selectedIds.length === 0) {
                    show_toastr('error', '{{ __('Please select at least one lead') }}', 'error');
                    return;
                }

                if (confirm('{{ __('Are you sure you want to delete') }} ' + selectedIds.length +
                        ' {{ __('leads') }}?\n\n' + selectedNames.join('\n'))) {
                    var deletePromises = [];
                    var csrfToken = $('meta[name="csrf-token"]').attr('content');

                    selectedIds.forEach(function(id) {
                        deletePromises.push(
                            $.ajax({
                                url: '{{ url('leads') }}/' + id,
                                type: 'POST',
                                data: {
                                    _token: csrfToken,
                                    _method: 'DELETE'
                                }
                            })
                        );
                    });

                    Promise.all(deletePromises).then(function() {
                        show_toastr('success', '{{ __('Leads successfully deleted!') }}',
                            'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }).catch(function(error) {
                        console.error('Delete error:', error);
                        show_toastr('error', '{{ __('Error deleting leads') }}', 'error');
                    });
                }
            });

            // Bulk reassign
            $(document).on('click', '#bulk-reassign-btn', function() {
                var selectedIds = [];

                $('.lead-select-checkbox:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    show_toastr('error', '{{ __('Please select at least one lead') }}', 'error');
                    return;
                }

                // Create a modal for user selection
                var modalHtml = `
                    <div class="modal fade" id="reassignModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">{{ __('Reassign Leads') }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>{{ __('Reassigning') }} <strong>` + selectedIds.length + `</strong> {{ __('leads') }}</p>
                                    <div class="form-group">
                                        <label>{{ __('Select Employee') }}</label>
                                        <select class="form-control" id="reassign-user-select">
                                            <option value="">{{ __('Select User') }}</option>
                                            @foreach ($users as $userId => $userName)
                                                <option value="{{ $userId }}">{{ $userName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                    <button type="button" class="btn btn-primary" id="confirm-reassign-btn">{{ __('Reassign') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                $('#reassignModal').remove();
                $('body').append(modalHtml);

                var modal = new bootstrap.Modal(document.getElementById('reassignModal'));
                modal.show();

                // Handle reassign confirmation
                $('#confirm-reassign-btn').off('click').on('click', function() {
                    var userId = $('#reassign-user-select').val();

                    if (!userId) {
                        show_toastr('error', '{{ __('Please select a user') }}', 'error');
                        return;
                    }

                    var csrfToken = $('meta[name="csrf-token"]').attr('content');

                    $.ajax({
                        url: '{{ route('leads.bulk.reassign') }}',
                        type: 'POST',
                        data: {
                            lead_ids: selectedIds,
                            user_id: userId,
                            _token: csrfToken
                        },
                        success: function(response) {
                            modal.hide();
                            show_toastr('success', response.message ||
                                '{{ __('Leads successfully reassigned!') }}',
                                'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        },
                        error: function(xhr) {
                            console.error('Reassign error:', xhr);
                            var errorMsg = '{{ __('Error reassigning leads') }}';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            show_toastr('error', errorMsg, 'error');
                        }
                    });
                });
            });
        });

        // Function to attach pagination click handler
        function attachPaginationHandler() {
            $(document).off('click', '.pagination a').on('click', '.pagination a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (url && window.currentFilterData) {
                    rememberLeadsPage(leadsPageFromUrl(url));
                    $.ajax({
                        // the paginator builds its links with page= alone, so the
                        // page size has to be put back on every hop
                        url: leadsUrlWith(url, { limit: savedLeadsLimit() }),
                        type: 'GET',
                        data: window.currentFilterData,
                        success: function(data) {
                            $('#kanban-view').html(data);
                            attachPaginationHandler();
                            setTimeout(function() {
                                cacheItems();
                                applyFilters();
                            }, 100);
                        }
                    });
                }
            });
        }

        $(document).ready(function() {
            var searchInput = document.getElementById('lead-search');
            var perPageSelect = document.getElementById('per-page-select');

            if (perPageSelect) {
                // Restore per-page value from URL or localStorage
                var urlParams = new URLSearchParams(window.location.search);
                var currentLimit = urlParams.get('limit') || localStorage.getItem('leads_per_page') || '25';
                perPageSelect.value = currentLimit;

                perPageSelect.addEventListener('change', function() {
                    localStorage.setItem('leads_per_page', this.value);
                    var url = new URL(window.location.href);
                    url.searchParams.set('limit', this.value);
                    url.searchParams.set('page', 1);
                    window.location.href = url.toString();
                });
            }

            // Simple search that triggers the advanced filter logic or reloads
            // Search by both name and phone number
            var searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        var searchValue = searchInput.value;
                        $('#filter_name').val(searchValue);
                        $('#filter_phone').val(searchValue);
                        $('#apply-filter').click();
                    }, 500);
                });
            }
        });

        var LEADS_FILTER_KEY = 'crm_leads_filter';

        // The board paginates over AJAX, so the page number never reaches the
        // address bar and nothing used to record it. Anything that reloads the
        // page -- saving a label is the usual one -- therefore came back at page
        // 1 no matter how deep you had gone.
        var LEADS_PAGE_KEY = 'crm_leads_page';

        function leadsPageFromUrl(url) {
            var m = /[?&]page=(\d+)/.exec(url || '');
            return m ? parseInt(m[1], 10) : 1;
        }

        function rememberLeadsPage(page) {
            try {
                if (page > 1) {
                    localStorage.setItem(LEADS_PAGE_KEY, page);
                } else {
                    localStorage.removeItem(LEADS_PAGE_KEY);
                }
            } catch (e) {
                // a private window with storage blocked still has to work
            }
        }

        function savedLeadsPage() {
            try {
                return parseInt(localStorage.getItem(LEADS_PAGE_KEY) || '1', 10) || 1;
            } catch (e) {
                return 1;
            }
        }

        // The per-page selector reloads the board with ?limit=N, but every later
        // request went out without it, so the server fell back to 25 and the
        // choice looked ignored.
        function savedLeadsLimit() {
            try {
                var el = document.getElementById('per-page-select');
                var value = parseInt((el && el.value) || localStorage.getItem('leads_per_page') || '25', 10);
                return value > 0 ? value : 25;
            } catch (e) {
                return 25;
            }
        }

        function leadsUrlWith(url, params) {
            Object.keys(params).forEach(function(key) {
                var value = params[key];
                if (!value || new RegExp('[?&]' + key + '=').test(url)) {
                    return;
                }
                url += (url.indexOf('?') === -1 ? '?' : '&') + key + '=' + encodeURIComponent(value);
            });

            return url;
        }

        function leadsFilterBadge(active) {
            var $btn = $('a[data-bs-target="#advancedFilterModal"]');
            if (active) {
                $btn.removeClass('btn-success').addClass('btn-warning');
            } else {
                $btn.removeClass('btn-warning').addClass('btn-success');
            }
        }

        function hasLeadsActiveFilter(data) {
            return Object.entries(data).some(function(e) {
                var k = e[0], v = e[1];
                if (k === 'unassigned' || k === 'no_activity') return v == 1;
                return v !== '' && v != null;
            });
        }

        function collectLeadsFilter() {
            // Collect data manually to join arrays with commas
            return {
                pipeline: $('#filter_pipeline').val(),
                deal_status: $('#filter_deal_status').val(),
                name: $('#filter_name').val(),
                phone: $('#filter_phone').val(),
                subject: $('#filter_subject').val(),
                country: $('#filter_country').val(),
                city: $('#filter_city').val(),
                campaign_name: $('#filter_campaign_name').val(),
                costing_type: $('#filter_costing_type').val(),
                gender: $('#filter_gender').val(),
                min_cost: $('#filter_min_cost').val(),
                max_cost: $('#filter_max_cost').val(),
                users: $('#filter_users').val() ? $('#filter_users').val().join(',') : '',
                products: $('#filter_products').val() ? $('#filter_products').val().join(',') : '',
                sources: $('#filter_sources').val() ? $('#filter_sources').val().join(',') : '',
                labels: $('#filter_labels').val() ? $('#filter_labels').val().join(',') : '',
                interests: $('#filter_interests').val() ? $('#filter_interests').val().join(',') : '',
                date: $('#filter_date').val(),
                updated_date: $('#filter_updated_date').val(),
                sales_group: $('#filter_sales_group').val(),
                unassigned: $('#filter_unassigned').is(':checked') ? 1 : 0,
                no_activity: $('#filter_no_activity').is(':checked') ? 1 : 0
            };
        }

        $(document).on('click', '#apply-filter', function() {
            var data = collectLeadsFilter();

            // Store filter data globally for pagination
            window.currentFilterData = data;
            localStorage.setItem(LEADS_FILTER_KEY, JSON.stringify(data));
            leadsFilterBadge(hasLeadsActiveFilter(data));
            rememberLeadsPage(1);

            runLeadsFilter(data);
        });

        // Puts a filter object on the board. Restoring a saved filter calls this
        // with the saved object rather than clicking #apply-filter, because that
        // handler reads the inputs back -- and custom.js has by then wrapped every
        // .select2 in a Choices widget, so those reads come back empty. Clicking it
        // on restore therefore wrote a blank filter over the saved one and asked
        // the server for every lead, which is exactly what "the filter is gone"
        // looked like.
        function runLeadsFilter(data, page) {
            var url = leadsUrlWith('{{ route('leads.index') }}', {
                page: page && page > 1 ? page : '',
                limit: savedLeadsLimit()
            });

            $.ajax({
                url: url,
                type: 'GET',
                data: data,
                success: function(data) {
                    $('#kanban-view').html(data);

                    // Attach pagination click handler
                    attachPaginationHandler();

                    // Reset pagination to page 1 and re-cache items
                    currentPage = 1;
                    setTimeout(function() {
                        cacheItems();
                        applyFilters();
                    }, 100);

                    // Re-init Dragula
                    if (window.dragula) {
                        ! function(a) {
                            "use strict";
                            var t = function() {
                                this.$body = a("body")
                            };
                            t.prototype.init = function() {
                                a('[data-plugin="dragula"]').each(function() {
                                    var t = a(this).data("containers"),
                                        n = [];
                                    if (t)
                                        for (var i = 0; i < t.length; i++) n.push(a("#" + t[
                                            i])[0]);
                                    else n = [a(this)[0]];
                                    var r = a(this).data("handleclass");
                                    r ? dragula(n, {
                                        moves: function(a, t, n) {
                                            return n.classList.contains(r)
                                        }
                                    }) : dragula(n).on('drop', function(el, target,
                                        source, sibling) {

                                        var order = [];
                                        $("#" + target.id + " > div").each(
                                            function() {
                                                order[$(this).index()] = $(this)
                                                    .attr('data-id');
                                            });

                                        var id = $(el).attr('data-id');
                                        var old_status = $("#" + source.id).data(
                                            'status');
                                        var new_status = $("#" + target.id).data(
                                            'status');
                                        var stage_id = $(target).attr('data-id');
                                        var pipeline_id = '{{ $pipeline->id }}';

                                        $("#" + source.id).parent().find('.count')
                                            .text($("#" + source.id + " > div")
                                                .length);
                                        $("#" + target.id).parent().find('.count')
                                            .text($("#" + target.id + " > div")
                                                .length);

                                        $.ajax({
                                            url: '{{ route('leads.order') }}',
                                            type: 'POST',
                                            data: {
                                                lead_id: id,
                                                stage_id: stage_id,
                                                order: order,
                                                new_status: new_status,
                                                old_status: old_status,
                                                pipeline_id: pipeline_id,
                                                "_token": $(
                                                    'meta[name="csrf-token"]'
                                                ).attr('content')
                                            },
                                            success: function(data) {
                                                show_toastr(data.status,
                                                    data.message,
                                                    data.status);
                                            },
                                            error: function(data) {
                                                data = data
                                                    .responseJSON;
                                                show_toastr(data.status,
                                                    data.message,
                                                    data.status)
                                            }
                                        });
                                    });
                                })
                            }, a.Dragula = new t, a.Dragula.init()
                        }(window.jQuery);
                    }
                }
            });
            $('#advancedFilterModal').modal('hide');
        }

        $(document).on('click', '#clear-filter', function() {
            localStorage.removeItem(LEADS_FILTER_KEY);
            rememberLeadsPage(1);
            window.location.reload();
        });

        $(document).on("change", "#filter_pipeline", function() {
            var currVal = $(this).val();
            getStages(currVal);
        });

        function getStages(id, callback) {
            $.ajax({
                url: '{{ route('leads.json') }}',
                data: {
                    pipeline_id: id,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                type: 'POST',
                success: function(data) {
                    // This block used to end with $("#filter_stage").select2(...).
                    // The theme ships Choices.js, not the select2 jQuery plugin, so
                    // that call threw "select2 is not a function" every time -- and
                    // it threw BEFORE the callback below, which is the line that
                    // re-applies the saved filter after a page reload. That is why
                    // the filter appeared to be wiped whenever anything reloaded the
                    // page, saving a label being the usual trigger.
                    try {
                        var $stage = $('#filter_stage').empty();
                        $stage.append($('<option></option>').attr('value', '').text(
                            "{{ __('Select Stage') }}"));
                        $.each(data, function(key, name) {
                            $stage.append($('<option></option>').attr('value', key).text(name));
                        });
                    } catch (err) {
                        console.log('stage list refresh failed:', err);
                    }

                    // outside the try: nothing about the dropdown may stop the
                    // filter from being restored again
                    if (typeof callback === 'function') callback();
                },
                error: function() {
                    if (typeof callback === 'function') callback();
                }
            })
        }

        function exportLeads(type) {
            var data = {
                type: type,
                pipeline: $('#filter_pipeline').val(),
                deal_status: $('#filter_deal_status').val(),
                name: $('#filter_name').val(),
                phone: $('#filter_phone').val(),
                subject: $('#filter_subject').val(),
                country: $('#filter_country').val(),
                city: $('#filter_city').val(),
                campaign_name: $('#filter_campaign_name').val(),
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
                date: $('#filter_date').val(),
                updated_date: $('#filter_updated_date').val(),
            };

            var queryString = $.param(data);
            window.location.href = "{{ route('leads.export') }}?" + queryString;
        }
    </script>
    <script>
        $(document).ready(function() {
            // #filter_campaign_name has class 'select2' and is handled by global select2() in custom.js

            // Created Date range picker
            flatpickr('#filter_date', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
                onReady: function(selectedDates, dateStr, instance) {
                    instance.calendarContainer.style.zIndex = 99999;
                }
            });

            // Last Updated Date range picker
            flatpickr('#filter_updated_date', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
                onReady: function(selectedDates, dateStr, instance) {
                    instance.calendarContainer.style.zIndex = 99999;
                }
            });

            // Restore saved filter on page load
            var _saved = localStorage.getItem(LEADS_FILTER_KEY);
            if (_saved) {
                try {
                    var _sf = JSON.parse(_saved);
                    if (_sf.deal_status) $('#filter_deal_status').val(_sf.deal_status);
                    if (_sf.name) $('#filter_name').val(_sf.name);
                    if (_sf.phone) $('#filter_phone').val(_sf.phone);
                    if (_sf.subject) $('#filter_subject').val(_sf.subject);
                    if (_sf.city) $('#filter_city').val(_sf.city);
                    if (_sf.min_cost) $('#filter_min_cost').val(_sf.min_cost);
                    if (_sf.max_cost) $('#filter_max_cost').val(_sf.max_cost);
                    if (_sf.country) $('#filter_country').val(_sf.country).trigger('change');
                    if (_sf.campaign_name) $('#filter_campaign_name').val(_sf.campaign_name).trigger('change');
                    if (_sf.costing_type) $('#filter_costing_type').val(_sf.costing_type).trigger('change');
                    if (_sf.gender) $('#filter_gender').val(_sf.gender).trigger('change');
                    if (_sf.users) $('#filter_users').val(_sf.users.split(',')).trigger('change');
                    if (_sf.products) $('#filter_products').val(_sf.products.split(',')).trigger('change');
                    if (_sf.sources) $('#filter_sources').val(_sf.sources.split(',')).trigger('change');
                    if (_sf.labels) $('#filter_labels').val(_sf.labels.split(',')).trigger('change');
                    if (_sf.interests) $('#filter_interests').val(_sf.interests.split(',')).trigger('change');
                    if (_sf.sales_group) $('#filter_sales_group').val(_sf.sales_group);
                    if (_sf.unassigned == 1) $('#filter_unassigned').prop('checked', true);
                    if (_sf.no_activity == 1) $('#filter_no_activity').prop('checked', true);
                    if (_sf.date) {
                        var _fp1 = document.getElementById('filter_date')._flatpickr;
                        if (_fp1) _fp1.setDate(_sf.date.includes(' to ') ? _sf.date.split(' to ') : [_sf.date], false);
                        else $('#filter_date').val(_sf.date);
                    }
                    if (_sf.updated_date) {
                        var _fp2 = document.getElementById('filter_updated_date')._flatpickr;
                        if (_fp2) _fp2.setDate(_sf.updated_date.includes(' to ') ? _sf.updated_date.split(' to ') : [_sf.updated_date], false);
                        else $('#filter_updated_date').val(_sf.updated_date);
                    }
                } catch(e) {
                    // Filling the boxes back in is a convenience and must never cost
                    // the filter itself. This used to call localStorage.removeItem(),
                    // so a single bad line threw the whole saved filter away.
                    console.log('could not fill the filter form:', e);
                }

                if (_sf && hasLeadsActiveFilter(_sf)) {
                    leadsFilterBadge(true);
                    window.currentFilterData = _sf;

                    // The saved object goes straight to the server. Nothing here
                    // reads the inputs back and nothing here writes to localStorage,
                    // so a Choices widget that has not caught up cannot blank the
                    // filter out.
                    runLeadsFilter(_sf, savedLeadsPage());

                    // Refreshing the stage list only changes what the dropdown shows.
                    if (_sf.pipeline) {
                        $('#filter_pipeline').val(_sf.pipeline);
                        getStages(_sf.pipeline);
                    }
                }
            }
        });
    </script>
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Lead') }}</li>
@endsection
@section('action-btn')
    <div class="float-end ">
        {{ Form::open(['route' => 'leads.change.pipeline', 'id' => 'change-pipeline', 'class' => 'btn btn-sm']) }}
        {{ Form::select('default_pipeline_id', $pipelines, $pipeline->id, ['class' => 'form-control select me-2', 'id' => 'default_pipeline_id']) }}
        {{ Form::close() }}

        <a href="{{ route('leads.list') }}" data-size="lg" data-bs-toggle="tooltip" title="{{ __('List View') }}"
            class="btn btn-sm bg-light-blue-subtitle me-1">
            <i class="ti ti-list"></i>
        </a>
        <a href="#" data-size="md" data-bs-toggle="tooltip" title="{{ __('Import') }}"
            data-url="{{ route('leads.import') }}" data-ajax-popup="true" data-title="{{ __('Import Leads') }}"
            class="btn btn-sm bg-brown-subtitle me-1">
            <i class="ti ti-file-import"></i>
        </a>
        <a href="#" class="btn btn-sm btn-secondary me-1" data-bs-toggle="dropdown" aria-expanded="false"
            title="{{ __('Export') }}">
            <i class="ti ti-file-export"></i>
        </a>
        <ul class="dropdown-menu">
            <li>
                <a class="dropdown-item" href="javascript:void(0)" onclick="exportLeads('xlsx')">
                    <i class="ti ti-file-export me-1"></i> {{ __('Export as XLSX') }}
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="javascript:void(0)" onclick="exportLeads('csv')">
                    <i class="ti ti-file-text me-1"></i> {{ __('Export as CSV') }}
                </a>
            </li>
        </ul>
        <a href="#" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#advancedFilterModal"
            title="{{ __('Advanced Filter') }}" data-bs-original-title="{{ __('Advanced Filter') }}">
            <i class="ti ti-filter"></i>
        </a>
        @if (!Auth::user()->hasRole('Sales Employee'))
            <a href="#" data-size="lg" data-url="{{ route('leads.create') }}" data-ajax-popup="true"
                data-bs-toggle="tooltip" title="{{ __('Create New Lead') }}" data-title="{{ __('Create Lead') }}"
                class="btn btn-sm btn-primary me-1">
                <i class="ti ti-plus"></i>
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-9 col-12 mb-2 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="text" id="lead-search" class="form-control"
                                    placeholder="{{ __('Search by name or phone...') }}">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div id="kanban-view">
                @include('leads.kanban_board')
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
                                    <label for="filter_pipeline" class="form-label">{{ __('Pipeline') }}</label>
                                    <select class="form-control select2" id="filter_pipeline" name="pipeline">
                                        <option value="">{{ __('Select Pipeline') }}</option>
                                        @foreach ($pipelines as $id => $name)
                                            <option value="{{ $id }}"
                                                @if ($pipeline->id == $id) selected @endif>{{ $name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_deal_status" class="form-label">{{ __('Deal status') }}</label>
                                    <select class="form-control" id="filter_deal_status" name="deal_status">
                                        <option value="">{{ __('Any') }}</option>
                                        <option value="{{ \App\Services\LeadScope::NO_DEAL }}">{{ __('No deal') }}</option>
                                        @foreach ($dealStatuses as $status)
                                            <option value="{{ $status }}">{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_name" class="form-label">{{ __('Name') }}</label>
                                    <input type="text" class="form-control" id="filter_name" name="name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_phone" class="form-label">{{ __('Phone') }}</label>
                                    <input type="text" class="form-control" id="filter_phone" name="phone"
                                        placeholder="{{ __('Search by phone number...') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_subject" class="form-label">{{ __('Subject') }}</label>
                                    <input type="text" class="form-control" id="filter_subject" name="subject">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_country" class="form-label">{{ __('Country') }}</label>
                                    <select class="form-control select2" id="filter_country" name="country">
                                        <option value="">{{ __('Select Country') }}</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country }}">{{ $country }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_city" class="form-label">{{ __('City') }}</label>
                                    <input type="text" class="form-control" id="filter_city" name="city">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_campaign_name"
                                        class="form-label">{{ __('Campaign Name') }}</label>
                                    <select class="form-control select2" id="filter_campaign_name" name="campaign_name">
                                        <option value="">{{ __('All Campaigns') }}</option>
                                        @foreach ($campaigns as $campaign)
                                            <option value="{{ $campaign }}" {{ request('campaign_name') == $campaign ? 'selected' : '' }}>{{ $campaign }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_costing_type" class="form-label">{{ __('Costing Type') }}</label>
                                    <select class="form-control select2" id="filter_costing_type" name="costing_type">
                                        <option value="">{{ __('Select Costing Type') }}</option>
                                        <option value="organic">{{ __('Organic') }}</option>
                                        <option value="paid">{{ __('Paid') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_gender" class="form-label">{{ __('Gender') }}</label>
                                    <select class="form-control select2" id="filter_gender" name="gender">
                                        <option value="">{{ __('Select Gender') }}</option>
                                        <option value="Male">{{ __('Male') }}</option>
                                        <option value="Female">{{ __('Female') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_min_cost" class="form-label">{{ __('Min Cost') }}</label>
                                    <input type="number" class="form-control" id="filter_min_cost" name="min_cost"
                                        step="0.01">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_max_cost" class="form-label">{{ __('Max Cost') }}</label>
                                    <input type="number" class="form-control" id="filter_max_cost" name="max_cost"
                                        step="0.01">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_users" class="form-label">{{ __('Users') }}</label>
                                    <select class="form-control select2" id="filter_users" name="users" multiple>
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
                                    <label for="filter_sources" class="form-label">{{ __('Sources') }}</label>
                                    <select class="form-control select2" id="filter_sources" name="sources" multiple>
                                        @foreach ($sources as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_labels" class="form-label">{{ __('Labels') }}</label>
                                    <select class="form-control select2" id="filter_labels" name="labels" multiple>
                                        @foreach ($labels as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_interests" class="form-label">{{ __('Interests') }}</label>
                                    <select class="form-control select2" id="filter_interests" name="interests" multiple>
                                        @foreach ($interests as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
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
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="filter_updated_date" class="form-label">
                                        <i class="ti ti-clock-edit me-1"></i>{{ __('Last Updated Date') }}
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                        <input type="text" class="form-control" id="filter_updated_date"
                                            name="updated_date" placeholder="{{ __('Select date range...') }}" autocomplete="off" readonly>
                                    </div>
                                    <small class="text-muted">{{ __('Filter leads by when they were last modified') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="filter_sales_group" class="form-label">{{ __('Sales Group') }}</label>
                                    <select class="form-control" id="filter_sales_group" name="sales_group">
                                        <option value="">{{ __('All Groups') }}</option>
                                        <option value="A">{{ __('Group A') }}</option>
                                        <option value="B">{{ __('Group B') }}</option>
                                        <option value="JOR">{{ __('Group JOR') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="filter_unassigned"
                                            name="unassigned" value="1">
                                        <label class="form-check-label" for="filter_unassigned">
                                            <i class="ti ti-user-off me-1"></i>{{ __('Unassigned Leads Only') }}
                                        </label>
                                    </div>
                                    <small class="text-muted">{{ __('Show leads not assigned to any employee') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="filter_no_activity"
                                            name="no_activity" value="1">
                                        <label class="form-check-label" for="filter_no_activity">
                                            <i class="ti ti-alert-triangle me-1"></i>{{ __('No Activity') }}
                                        </label>
                                    </div>
                                    <small
                                        class="text-muted">{{ __('Show leads with no actions or activities') }}</small>
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
