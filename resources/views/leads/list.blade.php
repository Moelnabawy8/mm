@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Leads') }} @if ($pipeline)
        - {{ $pipeline->name }}
    @endif
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/summernote/summernote-bs4.css') }}">
@endpush
@push('script-page')
    <script src="{{ asset('css/summernote/summernote-bs4.js') }}"></script>
    <script>
        $(document).on("change", ".change-pipeline select[name=default_pipeline_id]", function() {
            $('#change-pipeline').submit();
        });
    </script>
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Lead') }}</li>
@endsection
@section('action-btn')
    <div class="float-end">
        <a href="{{ route('leads.index') }}" data-bs-toggle="tooltip" title="{{ __('Kanban View') }}"
            class="btn btn-sm bg-light-blue-subtitle me-1">
            <i class="ti ti-layout-grid"></i>
        </a>
        <a href="#" data-size="md" data-bs-toggle="tooltip" title="{{ __('Import') }}"
            data-url="{{ route('leads.import') }}" data-ajax-popup="true" data-title="{{ __('Import Leads') }}"
            class="btn btn-sm bg-brown-subtitle me-1">
            <i class="ti ti-file-import"></i>
        </a>
        <a href="{{ route('leads.export') }}" data-bs-toggle="tooltip" title="{{ __('Export') }}"
            class="btn btn-sm btn-secondary me-1">
            <i class="ti ti-file-export"></i>
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
    @if ($pipeline)
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-body table-border-style">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-1 mb-3 border-bottom">
                            <div id="bulk-actions" class="d-none">
                                <div class="d-flex align-items-center gap-2 bg-warning bg-opacity-10 border border-warning rounded px-3 py-2">
                                    <i class="ti ti-checkbox text-warning"></i>
                                    <strong><span id="selected-count">0</span></strong>
                                    <span class="text-muted">{{ __('leads selected') }}</span>
                                    <button type="button" class="btn btn-sm btn-danger ms-2" id="bulk-delete-btn">
                                        <i class="ti ti-trash me-1"></i>{{ __('Delete Selected') }}
                                    </button>
                                </div>
                            </div>
                            <div class="ms-auto d-flex align-items-center gap-3">
                                <span class="text-muted small fw-semibold">{{ __('Showing') }} <strong>{{ $leads->count() }}</strong> {{ __('of') }} <strong>{{ $leads->total() }}</strong></span>
                                <select id="per-page-select" class="form-select form-select-sm" style="width: 130px;">
                                    <option value="25">25 {{ __('per page') }}</option>
                                    <option value="50">50 {{ __('per page') }}</option>
                                    <option value="100">100 {{ __('per page') }}</option>
                                    <option value="250">250 {{ __('per page') }}</option>
                                    <option value="500">500 {{ __('per page') }}</option>
                                    <option value="1000">1000 {{ __('per page') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="select-all-leads" class="form-check-input">
                                        </th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Email') }}</th>
                                        <th>{{ __('Phone') }}</th>
                                        <th>{{ __('Stage') }}</th>
                                        <th>{{ __('Users') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (count($leads) > 0)
                                        @foreach ($leads as $lead)
                                            @php
                                                $cleanPhone = !empty($lead->phone)
                                                    ? preg_replace('/[^0-9]/', '', $lead->phone)
                                                    : '';

                                                $labels = $lead->labels();
                                                $labelsString =
                                                    $labels && $labels instanceof \Illuminate\Support\Collection
                                                        ? $labels->pluck('name')->implode(', ')
                                                        : '';

                                                $sources = $lead->sources();
                                                $sourcesString =
                                                    $sources && $sources instanceof \Illuminate\Support\Collection
                                                        ? $sources->pluck('name')->implode(', ')
                                                        : '';

                                                $products = $lead->products();
                                                $productsString =
                                                    $products && $products instanceof \Illuminate\Support\Collection
                                                        ? $products->pluck('name')->implode(', ')
                                                        : '';

                                                $interests = $lead->interests();
                                                $interestsString =
                                                    $interests && $interests instanceof \Illuminate\Support\Collection
                                                        ? $interests->pluck('name')->implode(', ')
                                                        : '';
                                            @endphp
                                            <tr class="lead-row" style="cursor: pointer;" data-name="{{ $lead->name }}"
                                                data-email="{{ $lead->email }}" data-phone="{{ $lead->phone }}"
                                                data-clean-phone="{{ !empty($lead->phone) ? preg_replace('/[^0-9]/', '', $lead->phone) : '' }}"
                                                data-telegram="{{ $lead->telegram }}" data-gender="{{ $lead->gender }}"
                                                data-country="{{ $lead->country }}" data-city="{{ $lead->city }}"
                                                data-costing-type="{{ $lead->costing_type }}"
                                                data-campaign-name="{{ $lead->campaign_name }}"
                                                data-cost="{{ $lead->cost }}" data-labels="{{ $labelsString }}"
                                                data-source="{{ $sourcesString }}" data-products="{{ $productsString }}"
                                                data-interests="{{ $interestsString }}"
                                                data-created-at="{{ \Auth::user()->dateFormat($lead->created_at) }}">
                                                <td onclick="event.stopPropagation();">
                                                    <input type="checkbox" class="form-check-input lead-checkbox"
                                                        value="{{ $lead->id }}" data-lead-name="{{ $lead->name }}">
                                                </td>
                                                <td>{{ $lead->name }}</td>
                                                <td>{{ $lead->email }}</td>
                                                <td>{{ $lead->phone }}</td>
                                                <td>{{ !empty($lead->stage) ? $lead->stage->name : '-' }}</td>
                                                <td>
                                                    {{-- The name is printed, not hidden in a tooltip: this cell used the
                                                         Bootstrap 4 attributes (data-toggle / data-original-title) while the
                                                         theme initialises data-bs-toggle only, so the avatar carried no
                                                         readable name at all and an unassigned lead looked like an assigned
                                                         one. --}}
                                                    @forelse ($lead->users as $user)
                                                        <span class="d-inline-flex align-items-center me-2">
                                                            <img alt="{{ $user->name }}"
                                                                @if ($user->avatar) src="{{ asset('/storage/uploads/avatar/' . $user->avatar) }}" @else src="{{ asset('/storage/uploads/avatar/avatar.png') }}" @endif
                                                                class="rounded-circle me-1" width="25" height="25">
                                                            <span>{{ $user->name }}</span>
                                                        </span>
                                                    @empty
                                                        <span class="text-muted">-</span>
                                                    @endforelse
                                                </td>
                                                @if (Auth::user()->type != 'client')
                                                    <td class="Action">
                                                        <span>
                                                            @can('view lead')
                                                                @if ($lead->is_active)
                                                                    <div class="action-btn me-2">
                                                                        <a href="{{ route('leads.show', $lead->id) }}"
                                                                            class="mx-3 btn btn-sm align-items-center bg-warning"
                                                                            data-size="xl" data-bs-toggle="tooltip"
                                                                            title="{{ __('View') }}"
                                                                            data-title="{{ __('Lead Detail') }}">
                                                                            <i class="ti ti-eye text-white"></i>
                                                                        </a>
                                                                    </div>
                                                                @endif
                                                            @endcan
                                                            @can('edit lead')
                                                                <div class="action-btn me-2">
                                                                    <a href="#"
                                                                        class="mx-3 btn btn-sm align-items-center bg-info"
                                                                        data-url="{{ route('leads.edit', $lead->id) }}"
                                                                        data-ajax-popup="true" data-size="xl"
                                                                        data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                                                                        data-title="{{ __('Lead Edit') }}">
                                                                        <i class="ti ti-pencil text-white"></i>
                                                                    </a>
                                                                </div>
                                                            @endcan
                                                            @can('delete lead')
                                                                <div class="action-btn ">
                                                                    {!! Form::open([
                                                                        'method' => 'DELETE',
                                                                        'route' => ['leads.destroy', $lead->id],
                                                                        'id' => 'delete-form-' . $lead->id,
                                                                    ]) !!}
                                                                    <a href="#"
                                                                        class="mx-3 btn btn-sm  align-items-center bs-pass-para bg-danger"
                                                                        data-bs-toggle="tooltip" title="{{ __('Delete') }}"><i
                                                                            class="ti ti-trash text-white"></i></a>

                                                                    {!! Form::close() !!}
                                                                </div>
                                                            @endcan
                                                            <div class="action-btn me-2">
                                                                <a href="{{ route('quality-evaluations.index', ['lead_id' => $lead->id]) }}"
                                                                    class="mx-3 btn btn-sm align-items-center bg-success"
                                                                    data-bs-toggle="tooltip" title="{{ __('Quality Evaluation') }}">
                                                                    <i class="ti ti-clipboard-check text-white"></i>
                                                                </a>
                                                            </div>
                                                            <div class="action-btn me-2">
                                                                <a href="{{ route('support-requests.create', ['lead_id' => $lead->id]) }}"
                                                                    class="mx-3 btn btn-sm align-items-center bg-info"
                                                                    data-bs-toggle="tooltip" title="{{ __('Team Support') }}">
                                                                    <i class="ti ti-headset text-white"></i>
                                                                </a>
                                                            </div>

                                                    </span>
                                                    </td>
                                            @endif
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr class="font-style">
                                            <td colspan="7" class="text-center">{{ __('No data available in table') }}
                                            </td>
                                        </tr>
        @endif

        </tbody>
        </table>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-center">
                {{ $leads->onEachSide(1)->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
        </div>
        </div>
        </div>
        </div>
        </div>
        @endif

        {{-- Lead Details Modal --}}
        <div class="modal fade" id="leadDetailsModal" tabindex="-1" role="dialog" aria-labelledby="leadDetailsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leadDetailsModalLabel">{{ __('Lead Details') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="lead-details-content">
                            {{-- Content will be injected via JS --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @push('script-page')
        <script>
            $(document).ready(function() {
                var perPageSelect = document.getElementById('per-page-select');

                if (perPageSelect) {
                    var urlParams = new URLSearchParams(window.location.search);
                    perPageSelect.value = urlParams.get('limit') || localStorage.getItem('leads_per_page') || '25';

                    perPageSelect.addEventListener('change', function() {
                        localStorage.setItem('leads_per_page', this.value);
                        var url = new URL(window.location.href);
                        url.searchParams.set('limit', this.value);
                        url.searchParams.set('page', 1);
                        window.location.href = url.toString();
                    });
                }

                // Select all checkbox
                $('#select-all-leads').on('change', function() {
                    const isChecked = $(this).is(':checked');
                    $('.lead-checkbox:visible').prop('checked', isChecked);
                    updateBulkActions();
                });

                // Individual checkbox
                $(document).on('change', '.lead-checkbox', function() {
                    updateBulkActions();

                    // Update select-all state
                    const totalVisible = $('.lead-checkbox:visible').length;
                    const totalChecked = $('.lead-checkbox:visible:checked').length;
                    $('#select-all-leads').prop('checked', totalVisible > 0 && totalVisible === totalChecked);
                });

                // Bulk delete
                $('#bulk-delete-btn').on('click', function() {
                    const selectedIds = [];
                    const selectedNames = [];

                    $('.lead-checkbox:checked').each(function() {
                        selectedIds.push($(this).val());
                        selectedNames.push($(this).data('lead-name'));
                    });

                    if (selectedIds.length === 0) return;

                    if (confirm('{{ __('Are you sure you want to delete') }} ' + selectedIds.length +
                            ' {{ __('leads') }}?\n\n' + selectedNames.join('\n'))) {
                        $.ajax({
                            {{-- Guarded: an unguarded route() here took the whole
                                 list page down with a RouteNotFoundException the
                                 last time a deploy dropped this route. --}}
                            url: '{{ Route::has('leads.bulk.destroy') ? route('leads.bulk.destroy') : '' }}',
                            type: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                ids: selectedIds
                            },
                            success: function(response) {
                                show_toastr('success', response.message, 'success');
                                location.reload();
                            },
                            error: function(xhr) {
                                var msg = xhr.responseJSON && xhr.responseJSON.message
                                    ? xhr.responseJSON.message
                                    : '{{ __('Error deleting leads') }}';
                                show_toastr('error', msg, 'error');
                            }
                        });
                    }
                });
            });


            function updateBulkActions() {
                const selectedCount = $('.lead-checkbox:checked').length;
                $('#selected-count').text(selectedCount);

                if (selectedCount > 0) {
                    $('#bulk-actions').removeClass('d-none');
                } else {
                    $('#bulk-actions').addClass('d-none');
                }
            }

            $(document).on('click', '.lead-row', function(e) {
                // Prevent if clicking on action buttons or links
                if ($(e.target).closest('a, button, .Action').length) {
                    return;
                }

                var button = $(this);
                var data = {
                    name: button.data('name'),
                    email: button.data('email'),
                    phone: button.data('phone'),
                    cleanPhone: button.data('clean-phone'),
                    telegram: button.data('telegram'),
                    gender: button.data('gender'),
                    country: button.data('country'),
                    city: button.data('city'),
                    costingType: button.data('costing-type'),
                    memberId: button.data('member-id'),
                    campaignName: button.data('campaign-name'),
                    cost: button.data('cost'),
                    labels: button.data('labels'),
                    source: button.data('source'),
                    products: button.data('products'),
                    interests: button.data('interests'),
                    createdAt: button.data('created-at')
                };

                var html = '<div class="row g-3">';

                // Helper function for grid items
                function infoItem(icon, label, value, link = null, color = 'primary') {
                    var valStr = (value !== null && value !== undefined) ? String(value) : '';
                    var hasValue = valStr && valStr !== '-' && valStr.trim() !== '';
                    var content = hasValue ? value : '&nbsp;'; // Use space to keep height if needed, or just -

                    var containerStyle = hasValue ?
                        `background: rgba(var(--bs-${color}-rgb), 0.05); border: 1px solid rgba(var(--bs-${color}-rgb), 0.1);` :
                        `background: #f8f9fa; border: 1px dashed #ced4da; opacity: 0.6;`;

                    var iconClass = hasValue ? `text-${color}` : 'text-muted';
                    var textClass = hasValue ? 'text-dark fw-bold' : 'text-muted';

                    if (hasValue && link) {
                        content =
                            `<a href="${link}" target="_blank" class="${textClass} text-decoration-none text-break">${value}</a>`;
                    } else if (!hasValue) {
                        content = `<span class="${textClass} small f-st-italic">{{ __('Not Provided') }}</span>`;
                    } else {
                        content = `<span class="${textClass} text-break">${content}</span>`;
                    }

                    return `
                    <div class="col-md-6 col-12">
                        <div class="p-3 rounded-2 h-100 d-flex flex-column justify-content-center" style="${containerStyle}">
                            <div class="d-flex align-items-center mb-1 gap-2">
                                <div class="rounded-circle p-1 d-flex align-items-center justify-content-center" style="width:24px;height:24px;background:rgba(255,255,255,0.5);">
                                    <i class="ti ti-${icon} ${iconClass}" style="font-size:14px;"></i>
                                </div>
                                <span class="text-uppercase text-muted" style="font-size: 10px; letter-spacing: 0.5px;">${label}</span>
                            </div>
                            <div style="font-size: 13px; padding-left: 32px;">${content}</div>
                        </div>
                    </div>
                `;
                }

                // Name (Full Width)
                html += `
                <div class="col-12 text-center mb-2">
                    <h4 class="fw-bold text-primary mb-1">${data.name}</h4>
                    <span class="badge bg-light text-muted border"><i class="ti ti-clock me-1"></i>${data.createdAt}</span>
                </div>
            `;

                // Helper function for multiple phone/telegram numbers
                function multiLinkItem(icon, label, value, linkPrefix, cleanFn, color = 'primary') {
                    var valStr = (value !== null && value !== undefined) ? String(value) : '';
                    var hasValue = valStr && valStr !== '-' && valStr.trim() !== '';

                    var containerStyle = hasValue ?
                        `background: rgba(var(--bs-${color}-rgb), 0.05); border: 1px solid rgba(var(--bs-${color}-rgb), 0.1);` :
                        `background: #f8f9fa; border: 1px dashed #ced4da; opacity: 0.6;`;

                    var iconClass = hasValue ? `text-${color}` : 'text-muted';
                    var textClass = hasValue ? 'text-dark fw-bold' : 'text-muted';
                    var content = '';

                    if (hasValue) {
                        var items = valStr.split(',').map(s => s.trim()).filter(s => s !== '');
                        content = items.map(function(item) {
                            var cleanItem = cleanFn(item);
                            return `<a href="${linkPrefix}${cleanItem}" target="_blank" class="${textClass} text-decoration-none text-break d-block" style="margin-bottom: 2px;">${item}</a>`;
                        }).join('');
                    } else {
                        content = `<span class="${textClass} small f-st-italic">{{ __('Not Provided') }}</span>`;
                    }

                    return `
                    <div class="col-md-6 col-12">
                        <div class="p-3 rounded-2 h-100 d-flex flex-column justify-content-center" style="${containerStyle}">
                            <div class="d-flex align-items-center mb-1 gap-2">
                                <div class="rounded-circle p-1 d-flex align-items-center justify-content-center" style="width:24px;height:24px;background:rgba(255,255,255,0.5);">
                                    <i class="ti ti-${icon} ${iconClass}" style="font-size:14px;"></i>
                                </div>
                                <span class="text-uppercase text-muted" style="font-size: 10px; letter-spacing: 0.5px;">${label}</span>
                            </div>
                            <div style="font-size: 13px; padding-left: 32px;">${content}</div>
                        </div>
                    </div>
                `;
                }

                // Contact Info
                html += multiLinkItem('brand-whatsapp', 'WhatsApp', data.phone, 'https://wa.me/', function(p) {
                    return p.replace(/[^0-9]/g, '');
                }, 'success');
                html += infoItem('mail', 'Email', data.email, data.email ? 'mailto:' + data.email : null, 'danger');
                html += multiLinkItem('brand-telegram', 'Telegram', data.telegram, 'https://t.me/', function(t) {
                    var cleaned = t.trim();
                    if (cleaned.startsWith('@')) {
                        return cleaned.substring(1);
                    }
                    // If it looks like a phone number (just digits), ensure prefix if possible or just return cleaned
                    return cleaned.replace(/[^a-zA-Z0-9]/g, '');
                }, 'info');
                html += infoItem('map-pin', 'Location', [data.country, data.city].filter(Boolean).join(', '), null,
                    'warning');

                // Personal & Details
                html += infoItem('user', 'Gender', data.gender, null, 'secondary');
                html += infoItem('id', 'Member ID', data.memberId, null, 'dark');
                html += infoItem('coin', 'Costing Type', data.costingType, null, 'dark');
                html += infoItem('currency-dollar', 'Cost', data.cost, null, 'dark');
                html += infoItem('speakerphone', 'Campaign', data.campaignName, null, 'dark');

                // Full Width Sections
                function fullWidthItem(label, value, color) {
                    var hasValue = value && value !== '';
                    var containerStyle = hasValue ?
                        `background: rgba(var(--bs-${color}-rgb), 0.05); border: 1px solid rgba(var(--bs-${color}-rgb), 0.1);` :
                        `background: #f8f9fa; border: 1px dashed #ced4da; opacity: 0.6;`;

                    var content = hasValue ? value :
                        `<span class="text-muted small f-st-italic">{{ __('Not Provided') }}</span>`;

                    return `
                 <div class="col-12">
                    <div class="p-3 rounded-2" style="${containerStyle}">
                        <div class="d-flex align-items-center gap-2 mb-1">
                             <span class="text-uppercase text-muted" style="font-size: 10px; letter-spacing: 0.5px;">${label}</span>
                        </div>
                        <div class="fw-bold ${hasValue ? 'text-dark' : 'text-muted'}">${content}</div>
                    </div>
                </div>`;
                }

                html += fullWidthItem('Sources', data.source, 'info');
                html += fullWidthItem('Tags', data.labels, 'primary');
                html += fullWidthItem('Products', data.products, 'danger');
                html += fullWidthItem('Interests', data.interests, 'warning');

                html += '</div>'; // End row

                $('#lead-details-content').html(html);
                $('#leadDetailsModal').modal('show');
            });
        </script>
    @endpush
