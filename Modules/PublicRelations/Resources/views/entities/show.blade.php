@extends('layouts.admin')

@section('page-title')
    {{ $entity->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pr.index') }}">{{ __('Public Relationships') }}</a></li>
    <li class="breadcrumb-item">{{ $entity->name }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/dropzone.min.css') }}">
    <style>
        .attachment-card { border-radius: 10px; overflow: hidden; transition: all 0.2s; }
        .attachment-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .attachment-preview { width: 100%; height: 120px; object-fit: cover; background: #f8f9fa; }
        .attachment-icon { height: 120px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #6c757d; font-size: 2.5rem; }
        .attachment-info { padding: 0.75rem; background: #fff; }
        .attachment-name { font-size: 0.85rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .attachment-size { font-size: 0.75rem; color: #6c757d; }
        .attachment-actions { display: flex; gap: 0.5rem; padding: 0 0.75rem 0.75rem; }
        .pr-timeline { list-style: none; padding: 0; margin: 0; }
        .pr-timeline-item { position: relative; padding: 0 0 1.25rem 1.5rem; border-left: 2px solid #e9ecef; }
        .pr-timeline-item:last-child { border-left-color: transparent; padding-bottom: 0; }
        .pr-timeline-item::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #0d6efd; }
    </style>
@endpush

@push('script-page')
    <script src="{{ asset('assets/js/plugins/dropzone-amd-module.min.js') }}"></script>
    <script>
        Dropzone.autoDiscover = false;
        var prDropzone = new Dropzone("#pr-dropzone", {
            url: "{{ route('pr.attachments.upload', $entity->id) }}",
            maxFilesize: {{ (int) (config('publicrelations.attachments.max_size_kb', 10240) / 1024) }},
            acceptedFiles: ".jpeg,.jpg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx",
            addRemoveLinks: true,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(file, response) {
                if (response.is_success) { show_toastr('Success', response.success_msg, 'success'); location.reload(); }
            },
            error: function(file, response) {
                show_toastr('Error', (response && response.error) || 'Upload failed', 'error');
            }
        });

        $(document).on('click', '.pr-delete-attachment', function() {
            var attachmentId = $(this).data('id');
            Swal.fire({
                title: '{{ __('Are you sure?') }}',
                text: '{{ __('This action cannot be undone.') }}',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '{{ __('Yes, delete it!') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('pr') }}/{{ $entity->id }}/attachments/" + attachmentId,
                        type: 'DELETE',
                        data: { _token: $('meta[name="csrf-token"]').attr('content') },
                        success: function(response) {
                            if (response.is_success) {
                                show_toastr('Success', response.success_msg, 'success');
                                $('#attachment-' + attachmentId).fadeOut(300, function() { $(this).remove(); });
                            }
                        },
                        error: function() { show_toastr('Error', 'Delete failed', 'error'); }
                    });
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    <div class="float-end">
        @can('edit pr entity')
            <a href="#" data-url="{{ route('pr.edit', $entity->id) }}" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Edit Public Relationships Entity') }}" data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-pencil"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="crm-show-page">
        {{-- Hero Header --}}
        <div class="crm-hero">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="crm-hero-title">{{ $entity->name }}</h1>
                    <div class="crm-hero-meta">
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-building"></i> {{ $entity->type->label() }}
                        </span>
                        @if ($entity->region)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-map-pin"></i> {{ $entity->region->label() }}
                            </span>
                        @endif
                        @if ($entity->contact_person)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-user"></i> {{ $entity->contact_person }}
                                @if ($entity->position) ({{ $entity->position }}) @endif
                            </span>
                        @endif
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-calendar"></i> {{ \Auth::user()->dateFormat($entity->created_at) }}
                        </span>
                    </div>
                </div>
                <span class="crm-status {{ match($entity->status->value) { 'active', 'partner' => 'success', 'on_hold' => 'info', default => '' } }}">
                    {{ $entity->status->label() }}
                </span>
            </div>
        </div>

        <div class="crm-layout">
            {{-- Left: Quick Panel --}}
            <div class="crm-quick-panel">
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Information') }}</div>
                    <div class="crm-info-list">
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Type') }}</span>
                            <span class="crm-info-value">{{ $entity->type->label() }}</span>
                        </div>
                        @if ($entity->region)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Region') }}</span>
                                <span class="crm-info-value">{{ $entity->region->label() }}</span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Status') }}</span>
                            <span class="crm-info-value">{{ $entity->status->label() }}</span>
                        </div>
                        @if ($entity->phone)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Phone') }}</span>
                                <span class="crm-info-value">{{ $entity->phone }}</span>
                            </div>
                        @endif
                        @if ($entity->email)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Email') }}</span>
                                <span class="crm-info-value">{{ $entity->email }}</span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Created') }}</span>
                            <span class="crm-info-value">{{ \Auth::user()->dateFormat($entity->created_at) }}</span>
                        </div>
                    </div>
                </div>

                @if ($customFieldValues->isNotEmpty())
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Custom Fields') }}</div>
                        <div class="crm-info-list">
                            @foreach ($customFieldValues as $key => $field)
                                <div class="crm-info-item">
                                    <span class="crm-info-label">{{ $field['label'] }}</span>
                                    <span class="crm-info-value">{{ is_array($field['value']) ? implode(', ', $field['value']) : $field['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($entity->attachments->count() > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Attachments') }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="ti ti-paperclip text-muted"></i>
                            <span>{{ $entity->attachments->count() }} {{ __('files') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: Main Content --}}
            <div class="crm-main-content">
                {{-- Interaction Logs Timeline --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-timeline"></i> {{ __('Interaction Logs') }}
                            <span class="crm-content-count">{{ $entity->interactionLogs->count() }}</span>
                        </h5>
                        @can('create pr entity')
                            <a data-size="lg" data-url="{{ route('pr.logs.create', $entity->id) }}" data-ajax-popup="true"
                                data-title="{{ __('Add Interaction') }}" data-bs-toggle="tooltip"
                                title="{{ __('Add Interaction') }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-plus"></i>
                            </a>
                        @endcan
                    </div>
                    <div class="crm-content-body">
                        @if ($entity->interactionLogs->isNotEmpty())
                            <ul class="pr-timeline">
                                @foreach ($entity->interactionLogs as $log)
                                    <li class="pr-timeline-item">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                            <div>
                                                <strong>{{ $log->current_action }}</strong>
                                                <span class="badge bg-secondary ms-1">{{ $log->objective->label() }}</span>
                                                <div class="text-muted small">{{ \Auth::user()->dateFormat($log->interaction_date) }}</div>
                                                @if ($log->previousAction)
                                                    <div class="text-muted small">
                                                        {{ __('Follows') }}: {{ $log->previousAction->current_action }}
                                                        ({{ \Auth::user()->dateFormat($log->previousAction->interaction_date) }})
                                                    </div>
                                                @endif
                                                @if ($log->next_action)
                                                    <div class="small"><strong>{{ __('Next') }}:</strong> {{ $log->next_action }}</div>
                                                @endif
                                                @if ($log->notes)
                                                    <div class="small mt-1">{{ $log->notes }}</div>
                                                @endif
                                            </div>
                                            <div class="text-nowrap">
                                                @can('edit pr entity')
                                                    <a href="#" data-url="{{ route('pr.logs.edit', [$entity->id, $log->id]) }}"
                                                        data-ajax-popup="true" data-size="lg"
                                                        data-title="{{ __('Edit Interaction') }}" class="btn btn-sm btn-light">
                                                        <i class="ti ti-pencil"></i>
                                                    </a>
                                                @endcan
                                                @can('delete pr entity')
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['pr.logs.destroy', $entity->id, $log->id], 'class' => 'd-inline']) !!}
                                                    <button type="submit" class="btn btn-sm btn-light bs-pass-para">
                                                        <i class="ti ti-trash text-danger"></i>
                                                    </button>
                                                    {!! Form::close() !!}
                                                @endcan
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-timeline"></i>
                                <p>{{ __('No interactions logged yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Milestones --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-flag"></i> {{ __('Milestones') }}
                            <span class="crm-content-count">{{ $entity->milestones->count() }}</span>
                        </h5>
                        @can('create pr entity')
                            <a data-size="md" data-url="{{ route('pr.milestones.create', $entity->id) }}"
                                data-ajax-popup="true" data-title="{{ __('Add Milestone') }}" data-bs-toggle="tooltip"
                                title="{{ __('Add Milestone') }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-plus"></i>
                            </a>
                        @endcan
                    </div>
                    <div class="crm-content-body no-padding">
                        @if ($entity->milestones->isNotEmpty())
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Event') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th style="width: 100px;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($entity->milestones as $milestone)
                                        <tr>
                                            <td>{{ $milestone->event_type->label() }}</td>
                                            <td>{{ \Auth::user()->dateFormat($milestone->event_date) }}</td>
                                            <td><span class="badge bg-info">{{ $milestone->status->label() }}</span></td>
                                            <td>
                                                @can('edit pr entity')
                                                    <a href="#" data-url="{{ route('pr.milestones.edit', $milestone->id) }}"
                                                        data-ajax-popup="true" data-size="md"
                                                        data-title="{{ __('Edit Milestone') }}" class="btn btn-sm btn-light">
                                                        <i class="ti ti-pencil"></i>
                                                    </a>
                                                @endcan
                                                @can('delete pr entity')
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['pr.milestone.destroy', $milestone->id], 'class' => 'd-inline']) !!}
                                                    <button type="submit" class="btn btn-sm btn-light bs-pass-para">
                                                        <i class="ti ti-trash text-danger"></i>
                                                    </button>
                                                    {!! Form::close() !!}
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-flag"></i>
                                <p>{{ __('No milestones yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Attachments --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-paperclip"></i> {{ __('Attachments') }}
                            <span class="crm-content-count">{{ $entity->attachments->count() }}</span>
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        @can('edit pr entity')
                            <div class="mb-4">
                                <form action="{{ route('pr.attachments.upload', $entity->id) }}" class="dropzone"
                                    id="pr-dropzone"
                                    style="min-height: 150px; border: 2px dashed #dee2e6; border-radius: 8px; background: #f8f9fa;">
                                    @csrf
                                    <div class="dz-message" data-dz-message>
                                        <div class="text-center py-4">
                                            <i class="ti ti-cloud-upload fs-1 text-muted mb-2"></i>
                                            <p class="mb-0 text-muted">{{ __('Drop call screenshots, proposals or draft contracts here') }}</p>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endcan
                        <div id="attachments-list" class="row g-3">
                            @foreach ($entity->attachments as $attachment)
                                <div class="col-md-3 col-sm-4 col-6" id="attachment-{{ $attachment->id }}">
                                    <div class="card attachment-card mb-0 shadow-sm">
                                        @if ($attachment->file_type === 'image')
                                            <img src="{{ route('pr.attachments.serve', [$entity->id, $attachment->id]) }}"
                                                class="attachment-preview" alt="{{ $attachment->file_name }}">
                                        @else
                                            <div class="attachment-icon"><i class="ti ti-file-text"></i></div>
                                        @endif
                                        <div class="attachment-info">
                                            <div class="attachment-name" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</div>
                                            <div class="attachment-size">{{ $attachment->formatted_size }}</div>
                                        </div>
                                        <div class="attachment-actions">
                                            <a href="{{ route('pr.attachments.download', [$entity->id, $attachment->id]) }}"
                                                class="btn btn-sm btn-light" title="{{ __('Download') }}">
                                                <i class="ti ti-download"></i>
                                            </a>
                                            @can('edit pr entity')
                                                <button type="button" class="btn btn-sm btn-danger pr-delete-attachment"
                                                    data-id="{{ $attachment->id }}" title="{{ __('Delete') }}">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
