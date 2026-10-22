@php
    $lead_stages = $pipeline->leadStages;
    $json = [];
    foreach ($lead_stages as $lead_stage) {
        $json[] = 'task-list-' . $lead_stage->id;
    }
@endphp

@if (!Auth::user()->hasRole('Sales Employee'))
    {{-- Bulk Actions Bar --}}
    <div class="row mb-3">
        <div class="col-12 d-flex align-items-center mb-2">
            <div class="form-check me-3">
                <input type="checkbox" class="form-check-input" id="select-all-leads-kanban"
                    style="width: 18px; height: 18px; cursor: pointer;">
                <label class="form-check-label ms-2" for="select-all-leads-kanban"
                    style="cursor: pointer; font-weight: 600;">{{ __('Select All') }}</label>
            </div>
        </div>
    </div>

    <div class="row mb-3" id="bulk-actions-bar" style="display: none;">
        <div class="col-12">
            <div class="alert alert-warning d-flex justify-content-between align-items-center mb-0"
                style="border-radius: 12px;">
                <div>
                    <i class="ti ti-checkbox me-2"></i>
                    <strong><span id="selected-leads-count">0</span></strong> {{ __('leads selected') }}
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="bulk-reassign-btn">
                        <i class="ti ti-user-plus me-1"></i>{{ __('Reassign') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" id="bulk-delete-leads-btn">
                        <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" id="clear-selection-btn">
                        <i class="ti ti-x me-1"></i>{{ __('Clear') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @push('css-page')
        <style>
            /* Mobile Responsiveness Fixes */
            @media (max-width: 768px) {
                .kanban-board-container {
                    overflow-x: auto !important;
                    -webkit-overflow-scrolling: touch;
                }

                .task-list-items {
                    min-width: 100% !important;
                    max-width: 100% !important;
                }

                .sales-item {
                    min-width: auto !important;
                    max-width: 100% !important;
                }

                /* Ensure cards don't overflow */
                .card {
                    overflow: hidden;
                    word-wrap: break-word;
                }

                /* Make buttons stack on mobile */
                .action-btn-group {
                    flex-direction: column;
                    gap: 0.5rem;
                }

                /* Adjust padding for mobile */
                .px-3 {
                    padding-left: 1rem !important;
                    padding-right: 1rem !important;
                }
            }

            /* Prevent horizontal scroll */
            body {
                overflow-x: hidden;
            }

            .row {
                margin-left: 0;
                margin-right: 0;
            }
        </style>
    @endpush
@endif

<div class="row">
    <div class="col-12">
        <div class="row" style="display: flex; flex-wrap: wrap;">
        @php
            // Build a globally-sorted flat list of all leads
            // This ensures newest leads appear first regardless of which stage they're in
            if (isset($leads)) {
                // $leads is already sorted by CAST(COALESCE(leads.date, leads.created_at) AS DATETIME) DESC from the controller.
                // When a search/filter returns no matches this is an empty collection, so we correctly show "no data"
                // instead of falling back to listing every lead.
                $allKanbanLeads = $leads;
            } else {
                // Fallback (only when $leads was not provided at all): collect from all stages and sort manually
                $allKanbanLeads = collect();
                foreach ($lead_stages as $ls) {
                    $allKanbanLeads = $allKanbanLeads->merge($ls->lead());
                }
                $allKanbanLeads = $allKanbanLeads->sortByDesc(function ($l) {
                    $d = $l->date ?? $l->created_at;
                    return \Carbon\Carbon::parse($d)->timestamp;
                });
            }

            // Source and label names for the whole company, fetched once. Asking
            // each lead for its own used to cost two queries per card.
            $creatorId = \Auth::user()->creatorId();
            $sourceMap = \App\Models\Source::where('created_by', $creatorId)->get()->keyBy('id');
            $labelMap = \App\Models\Label::where('created_by', $creatorId)->get()->keyBy('id');

            $relatedByIds = function ($idList, $map) {
                if (empty($idList)) {
                    return collect();
                }
                return collect(explode(',', $idList))
                    ->map(function ($id) use ($map) {
                        return $map->get(trim($id));
                    })
                    ->filter()
                    ->values();
            };
        @endphp

        @foreach ($allKanbanLeads as $lead)
            @php
                $sources = $relatedByIds($lead->sources, $sourceMap);
                $currentType = $lead->getStageType();
                $searchable = $lead->name . ' ' . $lead->email . ' ' . $lead->phone . ' ' . $lead->subject;

                // Use eager-loaded counts
                $dealsCount = $lead->deals_count ?? 0;
                $salesMeetingsCount = $lead->sales_meetings_count ?? 0;
                $dealPurchasesCount = $lead->deal_purchases_count ?? 0;
                $installmentsCount = 0;

                // Get reassignment date from user_leads pivot table
                $reassignmentDate = null;
                try {
                    $currentUserLead = $lead->users->where('id', $lead->user_id)->first();
                    if ($currentUserLead && isset($currentUserLead->pivot->created_at)) {
                        $reassignmentDate = $currentUserLead->pivot->created_at;
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            @endphp
                    <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12 mb-4">
                        <div class="sales-item lead-card-item d-flex flex-column h-100" data-id="{{ $lead->id }}"
                            data-search="{{ strtolower($searchable) }}" data-lead-name="{{ $lead->name }}"
                            style="width: 100%; 
                                   background: #ffffff; 
                                   border-radius: 16px; 
                                   box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
                                   border: 1px solid rgba(0,0,0,0.06);
                                   transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                   overflow: hidden;">

                            <div class="sales-item-top pb-3 px-3 pt-3"
                                style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2" style="max-width: 85%;">
                                        @if (!Auth::user()->hasRole('Sales Employee'))
                                            <input type="checkbox" class="form-check-input lead-select-checkbox"
                                                value="{{ $lead->id }}" data-lead-name="{{ $lead->name }}"
                                                onclick="event.stopPropagation();"
                                                style="width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;">
                                        @endif
                                        <h5 class="mb-0 text-truncate"
                                            style="font-size: 15px; font-weight: 700; letter-spacing: -0.3px;">
                                            <a href="@can('view lead')@if ($lead->is_active){{ route('leads.show', $lead->id) }}@else#@endif @else#@endcan"
                                                class="dashboard-link text-dark"
                                                style="text-decoration: none; transition: color 0.2s;">{{ $lead->name }}</a>
                                        </h5>
                                    </div>
                                    @if (Auth::user()->type != 'client')
                                        <div class="dropdown">
                                            <button type="button" class="btn p-0 border-0 bg-transparent"
                                                data-bs-toggle="dropdown"
                                                style="width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                                                <i class="ti ti-dots-vertical text-muted" style="font-size: 18px;"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end shadow-lg border-0"
                                                style="border-radius: 12px; padding: 8px;">
                                                @if (!Auth::user()->hasRole('Sales Employee'))
                                                    @can('edit lead')
                                                        <a href="#!" data-size="md"
                                                            data-url="{{ URL::to('leads/' . $lead->id . '/labels') }}"
                                                            data-ajax-popup="true" class="dropdown-item"
                                                            style="border-radius: 8px; padding: 8px 12px;">
                                                            <i class="ti ti-bookmark me-2"></i>{{ __('Labels') }}
                                                        </a>
                                                        <a href="#!" data-size="lg"
                                                            data-url="{{ URL::to('leads/' . $lead->id . '/edit') }}"
                                                            data-ajax-popup="true" class="dropdown-item"
                                                            style="border-radius: 8px; padding: 8px 12px;">
                                                            <i class="ti ti-pencil me-2"></i>{{ __('Edit') }}
                                                        </a>
                                                    @endcan
                                                    @can('delete lead')
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['leads.destroy', $lead->id],
                                                            'id' => 'delete-form-' . $lead->id,
                                                        ]) !!}
                                                        <a href="#!" class="dropdown-item bs-pass-para text-danger"
                                                            style="border-radius: 8px; padding: 8px 12px;">
                                                            <i class="ti ti-trash me-2"></i>{{ __('Delete') }}
                                                        </a>
                                                        {!! Form::close() !!}
                                                    @endcan
                                                @else
                                                    {{-- Labels for Sales Employee --}}
                                                    @can('edit lead')
                                                        <a href="#!" data-size="md"
                                                            data-url="{{ URL::to('leads/' . $lead->id . '/labels') }}"
                                                            data-ajax-popup="true" class="dropdown-item"
                                                            style="border-radius: 8px; padding: 8px 12px;">
                                                            <i class="ti ti-bookmark me-2"></i>{{ __('Labels') }}
                                                        </a>
                                                    @endcan
                                                @endif
                                                <div class="dropdown-divider my-1"></div>
                                                <a href="{{ route('quality-evaluations.index', ['lead_id' => $lead->id]) }}"
                                                    class="dropdown-item"
                                                    style="border-radius: 8px; padding: 8px 12px;">
                                                    <i class="ti ti-clipboard-check me-2 text-success"></i>{{ __('Quality Evaluation') }}
                                                </a>
                                                <a href="{{ route('support-requests.create', ['lead_id' => $lead->id]) }}"
                                                    class="dropdown-item"
                                                    style="border-radius: 8px; padding: 8px 12px;">
                                                    <i class="ti ti-headset me-2 text-info"></i>{{ __('Team Support') }}
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center" style="min-height: 28px;">
                                    @php
                                        $labels = $relatedByIds($lead->labels, $labelMap);
                                    @endphp
                                    {{-- Stage Badge --}}
                                    @if ($lead->stage)
                                        <span class="badge"
                                            style="font-size: 9px; font-weight: 700; padding: 3px 8px; border-radius: 20px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; letter-spacing: 0.3px;">
                                            {{ $lead->stage->name }}
                                        </span>
                                    @endif
                                    @if ($labels)
                                        @foreach ($labels as $label)
                                            <span class="badge bg-light-{{ $label->color }} text-{{ $label->color }}"
                                                style="font-size: 10px; 
                                                         font-weight: 600; 
                                                         padding: 4px 10px; 
                                                         border-radius: 6px;
                                                         letter-spacing: 0.3px;">
                                                {{ $label->name }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            {{-- Lead Info Grid --}}
                            <div class="lead-info-card flex-grow-1 py-3 px-3">
                                <div class="row g-2">
                                    {{-- WhatsApp --}}
                                    <div class="col-6">
                                        @php
                                            $phones = array_filter(preg_split('/[, \-\/]+/', $lead->phone));
                                        @endphp
                                        <div class="p-2 h-100 d-flex flex-column justify-content-center"
                                            style="background: {{ !empty($lead->phone) ? 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)' : '#f8f9fa' }}; 
                                                    border-radius: 10px;
                                                    border: 1px solid {{ !empty($lead->phone) ? 'rgba(40, 167, 69, 0.2)' : 'rgba(0,0,0,0.06)' }};
                                                    transition: transform 0.2s;">
                                            <div class="d-flex align-items-center mb-1 gap-1">
                                                <i class="ti ti-brand-whatsapp {{ !empty($lead->phone) ? 'text-success' : 'text-muted' }}"
                                                    style="font-size: 14px;"></i>
                                                <span class="text-uppercase"
                                                    style="font-size: 8px; letter-spacing: 0.8px; font-weight: 700; color: #6c757d;">{{ __('WhatsApp') }}</span>
                                            </div>
                                            @if (count($phones) > 0)
                                                @foreach ($phones as $phone)
                                                    @php($cleanPhone = preg_replace('/[^0-9]/', '', $phone))
                                                    <a href="https://wa.me/{{ $cleanPhone }}" target="_blank"
                                                        class="text-dark fw-bold text-truncate text-decoration-none d-block"
                                                        style="font-size: 11px; line-height: 1.3;">{{ $phone }}</a>
                                                @endforeach
                                            @else
                                                <span class="text-muted" style="font-size: 11px;">-</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Email --}}
                                    <div class="col-6">
                                        <div class="p-2 h-100 d-flex flex-column justify-content-center"
                                            style="background: {{ !empty($lead->email) ? 'linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%)' : '#f8f9fa' }}; 
                                                    border-radius: 10px;
                                                    border: 1px solid {{ !empty($lead->email) ? 'rgba(220, 53, 69, 0.2)' : 'rgba(0,0,0,0.06)' }};
                                                    transition: transform 0.2s;">
                                            <div class="d-flex align-items-center mb-1 gap-1">
                                                <i class="ti ti-mail {{ !empty($lead->email) ? 'text-danger' : 'text-muted' }}"
                                                    style="font-size: 14px;"></i>
                                                <span class="text-uppercase"
                                                    style="font-size: 8px; letter-spacing: 0.8px; font-weight: 700; color: #6c757d;">{{ __('Email') }}</span>
                                            </div>
                                            @if (!empty($lead->email))
                                                <a href="mailto:{{ $lead->email }}" title="{{ $lead->email }}"
                                                    class="text-dark fw-bold text-truncate text-decoration-none"
                                                    style="font-size: 11px; line-height: 1.3;">{{ $lead->email }}</a>
                                            @else
                                                <span class="text-muted" style="font-size: 11px;">-</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Telegram --}}
                                    <div class="col-6">
                                        <div class="p-2 h-100 d-flex flex-column justify-content-center"
                                            style="background: {{ !empty($lead->telegram) ? 'linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%)' : '#f8f9fa' }}; 
                                                    border-radius: 10px;
                                                    border: 1px solid {{ !empty($lead->telegram) ? 'rgba(23, 162, 184, 0.2)' : 'rgba(0,0,0,0.06)' }};
                                                    transition: transform 0.2s;">
                                            <div class="d-flex align-items-center mb-1 gap-1">
                                                <i class="ti ti-brand-telegram {{ !empty($lead->telegram) ? 'text-info' : 'text-muted' }}"
                                                    style="font-size: 14px;"></i>
                                                <span class="text-uppercase"
                                                    style="font-size: 8px; letter-spacing: 0.8px; font-weight: 700; color: #6c757d;">{{ __('Telegram') }}</span>
                                            </div>
                                            @if (!empty($lead->telegram))
                                                <a href="https://t.me/{{ ltrim($lead->telegram, '@') }}"
                                                    target="_blank"
                                                    class="text-dark fw-bold text-truncate text-decoration-none"
                                                    style="font-size: 11px; line-height: 1.3;">{{ $lead->telegram }}</a>
                                            @else
                                                <span class="text-muted" style="font-size: 11px;">-</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Gender --}}
                                    <div class="col-6">
                                        <div class="p-2 h-100 d-flex flex-column justify-content-center"
                                            style="background: {{ !empty($lead->gender) ? 'linear-gradient(135deg, #cfe2ff 0%, #b6d4fe 100%)' : '#f8f9fa' }}; 
                                                    border-radius: 10px;
                                                    border: 1px solid {{ !empty($lead->gender) ? 'rgba(13, 110, 253, 0.2)' : 'rgba(0,0,0,0.06)' }};
                                                    transition: transform 0.2s;">
                                            <div class="d-flex align-items-center mb-1 gap-1">
                                                <i class="ti ti-user {{ !empty($lead->gender) ? 'text-primary' : 'text-muted' }}"
                                                    style="font-size: 14px;"></i>
                                                <span class="text-uppercase"
                                                    style="font-size: 8px; letter-spacing: 0.8px; font-weight: 700; color: #6c757d;">{{ __('Gender') }}</span>
                                            </div>
                                            <span class="text-dark fw-bold text-truncate"
                                                style="font-size: 11px; line-height: 1.3;">{{ $lead->gender ?? '-' }}</span>
                                        </div>
                                    </div>

                                    {{-- Location --}}
                                    <div class="col-6">
                                        <div class="p-2 h-100 d-flex flex-column justify-content-center"
                                            style="background: {{ !empty($lead->country) ? 'linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%)' : '#f8f9fa' }}; 
                                                    border-radius: 10px;
                                                    border: 1px solid {{ !empty($lead->country) ? 'rgba(255, 193, 7, 0.3)' : 'rgba(0,0,0,0.06)' }};
                                                    transition: transform 0.2s;">
                                            <div class="d-flex align-items-center mb-1 gap-1">
                                                <i class="ti ti-map-pin {{ !empty($lead->country) ? 'text-warning' : 'text-muted' }}"
                                                    style="font-size: 14px;"></i>
                                                <span class="text-uppercase"
                                                    style="font-size: 8px; letter-spacing: 0.8px; font-weight: 700; color: #6c757d;">{{ __('Country') }}</span>
                                            </div>
                                            <span class="text-dark fw-bold text-truncate"
                                                style="font-size: 11px; line-height: 1.3;">{{ $lead->country ?? '-' }}</span>
                                        </div>
                                    </div>

                                    {{-- Source --}}
                                    <div class="col-6">
                                        <div class="p-2 h-100 d-flex flex-column justify-content-center"
                                            style="background: {{ count($sources) > 0 ? 'linear-gradient(135deg, #d4f4f4 0%, #b8ecec 100%)' : '#f8f9fa' }}; 
                                                    border-radius: 10px;
                                                    border: 1px solid {{ count($sources) > 0 ? 'rgba(13, 202, 240, 0.2)' : 'rgba(0,0,0,0.06)' }};
                                                    transition: transform 0.2s;">
                                            <div class="d-flex align-items-center mb-1 gap-1">
                                                <i class="ti ti-world {{ count($sources) > 0 ? 'text-cyan' : 'text-muted' }}"
                                                    style="font-size: 14px;"></i>
                                                <span class="text-uppercase"
                                                    style="font-size: 8px; letter-spacing: 0.8px; font-weight: 700; color: #6c757d;">{{ __('Source') }}</span>
                                            </div>
                                            <span class="text-dark fw-bold text-truncate"
                                                style="font-size: 11px; line-height: 1.3;">
                                                @if (count($sources) > 0)
                                                    {{ $sources->first()->name }}
                                                @else
                                                    -
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            {{-- Footer Icons & Date --}}
                            <div class="sales-item-bottom pt-3 pb-3 px-3 mt-auto"
                                style="background: #fafbfc; border-top: 1px solid rgba(0,0,0,0.06);">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex flex-column gap-1">
                                        <div class="text-muted d-flex align-items-center gap-1"
                                            style="font-size: 10px; font-weight: 600;">
                                            <i class="ti ti-calendar" style="font-size: 13px;"></i>
                                            <span>{{ !empty($lead->date) ? \Auth::user()->dateFormat($lead->date) : \Auth::user()->dateFormat($lead->created_at) }}</span>
                                        </div>
                                        @if ($reassignmentDate)
                                            <div class="text-primary d-flex align-items-center gap-1"
                                                style="font-size: 9px; font-weight: 600;" data-bs-toggle="tooltip"
                                                title="{{ __('Assigned to current employee') }}">
                                                <i class="ti ti-user-check" style="font-size: 12px;"></i>
                                                <span>{{ \Auth::user()->dateFormat($reassignmentDate) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="user-group d-flex" style="gap: -8px;">
                                        @foreach ($lead->users as $user)
                                            <a href="#" class="btn btn-sm mr-1 p-0 rounded-circle">
                                                <img alt="image" data-bs-toggle="tooltip"
                                                    title="{{ $user->name }}"
                                                    @if ($user->avatar) src="{{ asset('/storage/uploads/avatar/' . $user->avatar) }}" @else src="{{ asset('/storage/uploads/avatar/avatar.png') }}" @endif
                                                    class="rounded-circle"
                                                    style="width: 26px; height: 26px; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            </a>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center gap-2"
                                    style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); 
                                            border-radius: 12px;
                                            padding: 12px 8px;
                                            border: 1px solid rgba(0,0,0,0.06);">
                                    {{-- Deal Icon --}}
                                    <div class="text-center" data-bs-toggle="tooltip"
                                        title="{{ __('Deals') }}: {{ $dealsCount }}"
                                        style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 6px; border-radius: 8px; transition: all 0.2s; background: {{ $dealsCount > 0 ? 'rgba(25, 135, 84, 0.15)' : 'transparent' }};">
                                        <i class="ti ti-briefcase {{ $dealsCount > 0 ? 'text-success' : 'text-secondary' }}"
                                            style="font-size: 18px;"></i>
                                        <span class="badge {{ $dealsCount > 0 ? 'bg-success' : 'bg-secondary' }}"
                                            style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; min-width: 20px;">{{ $dealsCount }}</span>
                                    </div>
                                    {{-- Sales Meeting Icon --}}
                                    <div class="text-center" data-bs-toggle="tooltip"
                                        title="{{ __('Sales Meetings') }}: {{ $salesMeetingsCount }}"
                                        style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 6px; border-radius: 8px; transition: all 0.2s; background: {{ $salesMeetingsCount > 0 ? 'rgba(255, 193, 7, 0.15)' : 'transparent' }};">
                                        <i class="ti ti-calendar-event {{ $salesMeetingsCount > 0 ? 'text-warning' : 'text-secondary' }}"
                                            style="font-size: 18px;"></i>
                                        <span
                                            class="badge {{ $salesMeetingsCount > 0 ? 'bg-warning' : 'bg-secondary' }}"
                                            style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; min-width: 20px;">{{ $salesMeetingsCount }}</span>
                                    </div>
                                    {{-- Deal Purchase Icon --}}
                                    <div class="text-center" data-bs-toggle="tooltip"
                                        title="{{ __('Deal Purchases') }}: {{ $dealPurchasesCount }}"
                                        style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 6px; border-radius: 8px; transition: all 0.2s; background: {{ $dealPurchasesCount > 0 ? 'rgba(13, 202, 240, 0.15)' : 'transparent' }};">
                                        <i class="ti ti-shopping-cart {{ $dealPurchasesCount > 0 ? 'text-info' : 'text-secondary' }}"
                                            style="font-size: 18px;"></i>
                                        <span class="badge {{ $dealPurchasesCount > 0 ? 'bg-info' : 'bg-secondary' }}"
                                            style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; min-width: 20px;">{{ $dealPurchasesCount }}</span>
                                    </div>
                                    {{-- Installments Icon --}}
                                    <div class="text-center" data-bs-toggle="tooltip"
                                        title="{{ __('Installments') }}: {{ $installmentsCount }}"
                                        style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 6px; border-radius: 8px; transition: all 0.2s; background: {{ $installmentsCount > 0 ? 'rgba(220, 53, 69, 0.15)' : 'transparent' }};">
                                        <i class="ti ti-credit-card {{ $installmentsCount > 0 ? 'text-danger' : 'text-secondary' }}"
                                            style="font-size: 18px;"></i>
                                        <span
                                            class="badge {{ $installmentsCount > 0 ? 'bg-danger' : 'bg-secondary' }}"
                                            style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; min-width: 20px;">{{ $installmentsCount }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($allKanbanLeads->isEmpty())
                    <div class="col-12">
                        <div class="text-center text-muted py-5">
                            <i class="ti ti-database-off" style="font-size: 48px; opacity: 0.4;"></i>
                            <h5 class="mt-3 mb-0">{{ __('No data found') }}</h5>
                            <p class="mb-0" style="font-size: 13px;">{{ __('No leads match your search.') }}</p>
                        </div>
                    </div>
                @endif
        </div>
        @if (isset($leads) && $leads instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $leads->total() > 0)
            <div class="mt-4 d-flex justify-content-center">
                {{ $leads->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
