@extends('layouts.admin')
@section('page-title')
    {{ $deal->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('deals.index') }}">{{ __('Deals') }}</a></li>
    <li class="breadcrumb-item">{{ $deal->name }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('css/summernote/summernote-bs4.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dropzone.min.css') }}">
    <style>
        .attachment-card {
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .attachment-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .attachment-preview {
            width: 100%;
            height: 120px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .attachment-icon {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 2.5rem;
        }

        .attachment-info {
            padding: 0.75rem;
            background: #fff;
        }

        .attachment-name {
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-size {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .attachment-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0 0.75rem 0.75rem;
        }
    </style>
@endpush

@push('script-page')
    <script src="{{ asset('css/summernote/summernote-bs4.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dropzone-amd-module.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            @can('edit deal')
                $('.summernote-simple').on('summernote.blur', function() {
                    $.ajax({
                        url: "{{ route('deals.note.store', $deal->id) }}",
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            notes: $(this).val()
                        },
                        type: 'POST',
                        success: function(response) {
                            if (response.is_success) {
                                show_toastr('Success', response.success, 'success');
                            } else {
                                show_toastr('error', response.error, 'error');
                            }
                        },
                        error: function(response) {
                            response = response.responseJSON;
                            if (response.is_success) {
                                show_toastr('error', response.error, 'error');
                            } else {
                                show_toastr('error', response.error, 'error');
                            }
                        }
                    })
                });
            @else
                $('.summernote-simple').summernote('disable');
            @endcan
        });

        Dropzone.autoDiscover = false;
        var dealDropzone = new Dropzone("#deal-dropzone", {
            url: "{{ route('deals.attachment.upload', $deal->id) }}",
            maxFilesize: 10,
            acceptedFiles: ".jpeg,.jpg,.png,.gif,.webp,.txt,.doc,.docx,.pdf",
            addRemoveLinks: true,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(file, response) {
                if (response.is_success) {
                    show_toastr('Success', response.success_msg, 'success');
                    location.reload();
                }
            },
            error: function(file, response) {
                show_toastr('Error', response.message || 'Upload failed', 'error');
            }
        });

        $(document).on('click', '.delete-attachment', function() {
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
                        url: "{{ url('deals') }}/{{ $deal->id }}/attachments/" +
                            attachmentId,
                        type: 'DELETE',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.is_success) {
                                show_toastr('Success', response.success_msg, 'success');
                                $('#attachment-' + attachmentId).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                        },
                        error: function(response) {
                            show_toastr('Error', 'Delete failed', 'error');
                        }
                    });
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    <div class="float-end">
        @can('edit deal')
            <a href="#" data-url="{{ route('deals.edit', $deal->id) }}" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Edit Deal') }}" data-bs-toggle="tooltip" title="{{ __('Edit') }}"
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
                    <h1 class="crm-hero-title">{{ $deal->name }}</h1>
                    <div class="crm-hero-meta">
                        @if ($deal->lead)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-user"></i>
                                <a href="{{ route('leads.show', $deal->lead->id) }}">{{ $deal->lead->name }}</a>
                            </span>
                        @endif
                        <span class="crm-hero-meta-item">
                            <i class="ti ti-calendar"></i>
                            {{ \Auth::user()->dateFormat($deal->created_at) }}
                        </span>
                        @if ($deal->datetime)
                            <span class="crm-hero-meta-item">
                                <i class="ti ti-clock"></i>
                                {{ \Auth::user()->dateFormat($deal->datetime) }} {{ $deal->datetime->format('h:i A') }}
                            </span>
                        @endif
                    </div>
                </div>
                @php($dealStageName = $deal->stage ? $deal->stage->name : null)
                @if ($dealStageName)
                    @if ($dealStageName == 'Won')
                        <span class="crm-status success"><i class="ti ti-check"></i> {{ __('Won') }}</span>
                    @elseif($dealStageName == 'Loss')
                        <span class="crm-status" style="background: rgba(220,53,69,0.1); color: #dc3545;"><i
                                class="ti ti-x"></i> {{ __('Loss') }}</span>
                    @else
                        <span class="crm-status info">{{ $dealStageName }}</span>
                    @endif
                @endif
            </div>
        </div>

        {{-- Two Column Layout --}}
        <div class="crm-layout">
            {{-- Left: Quick Panel --}}
            <div class="crm-quick-panel">
                {{-- Quick Info --}}
                <div class="crm-quick-card">
                    <div class="crm-quick-card-title">{{ __('Information') }}</div>
                    <div class="crm-info-list">
                        @if ($deal->lead)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Lead') }}</span>
                                <span class="crm-info-value">
                                    <a href="{{ route('leads.show', $deal->lead->id) }}">{{ $deal->lead->name }}</a>
                                </span>
                            </div>
                        @endif
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Status') }}</span>
                            <span class="crm-info-value">{{ $deal->stage ? $deal->stage->name : __('Pending') }}</span>
                        </div>
                        <div class="crm-info-item">
                            <span class="crm-info-label">{{ __('Created') }}</span>
                            <span class="crm-info-value">{{ \Auth::user()->dateFormat($deal->created_at) }}</span>
                        </div>
                        @if ($deal->datetime)
                            <div class="crm-info-item">
                                <span class="crm-info-label">{{ __('Date & Time') }}</span>
                                <span class="crm-info-value">{{ $deal->datetime->format('M d, Y h:i A') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Products Summary --}}
                <?php $dealProducts = $deal->products(); ?>
                @if ($dealProducts && count($dealProducts) > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Products') }} ({{ count($dealProducts) }})</div>
                        <div class="d-flex flex-column gap-1">
                            @foreach (collect($dealProducts)->take(5) as $product)
                                <div class="d-flex justify-content-between small">
                                    <span>{{ $product->name }}</span>
                                    <span class="text-muted">{{ \Auth::user()->priceFormat($product->sale_price) }}</span>
                                </div>
                            @endforeach
                            @if (count($dealProducts) > 5)
                                <small class="text-muted mt-1">+{{ count($dealProducts) - 5 }}
                                    {{ __('more') }}</small>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Attachments Count --}}
                @if ($deal->attachments && $deal->attachments->count() > 0)
                    <div class="crm-quick-card">
                        <div class="crm-quick-card-title">{{ __('Attachments') }}</div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="ti ti-paperclip text-muted"></i>
                            <span>{{ $deal->attachments->count() }} {{ __('files') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right: Main Content --}}
            <div class="crm-main-content">
                {{-- Products Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-package"></i>
                            {{ __('Products') }}
                            @if ($dealProducts && count($dealProducts) > 0)
                                <span class="crm-content-count">{{ count($dealProducts) }}</span>
                            @endif
                        </h5>
                        <a data-size="md" data-url="{{ route('deals.products.edit', $deal->id) }}" data-ajax-popup="true"
                            data-bs-toggle="tooltip" title="{{ __('Add Product') }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-plus"></i>
                        </a>
                    </div>
                    <div class="crm-content-body no-padding">
                        @if ($dealProducts && count($dealProducts) > 0)
                            <table class="crm-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('SKU') }}</th>
                                        <th>{{ __('Price') }}</th>
                                        <th style="width: 80px;">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dealProducts as $product)
                                        <tr>
                                            <td>{{ $product->name }}</td>
                                            <td>{{ $product->sku ?? '-' }}</td>
                                            <td>{{ \Auth::user()->priceFormat($product->sale_price) }}</td>
                                            <td>
                                                {!! Form::open([
                                                    'method' => 'DELETE',
                                                    'route' => ['deals.products.destroy', $deal->id, $product->id],
                                                    'class' => 'd-inline',
                                                ]) !!}
                                                <button type="submit" class="btn btn-sm btn-danger bs-pass-para"
                                                    data-bs-toggle="tooltip" title="{{ __('Remove') }}">
                                                    <i class="ti ti-trash text-white"></i>
                                                </button>
                                                {!! Form::close() !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="crm-empty">
                                <i class="ti ti-package"></i>
                                <p>{{ __('No products yet') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Notes Section --}}
                <div class="crm-content-section crm-notes-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-notes"></i>
                            {{ __('Notes') }}
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <textarea class="summernote-simple" name="note">{!! $deal->notes !!}</textarea>
                    </div>
                </div>

                {{-- Attachments Section --}}
                <div class="crm-content-section">
                    <div class="crm-content-header">
                        <h5 class="crm-content-title">
                            <i class="ti ti-paperclip"></i>
                            {{ __('Attachments') }}
                            @if ($deal->attachments && $deal->attachments->count() > 0)
                                <span class="crm-content-count">{{ $deal->attachments->count() }}</span>
                            @endif
                        </h5>
                    </div>
                    <div class="crm-content-body">
                        <div class="mb-4">
                            <form action="{{ route('deals.attachment.upload', $deal->id) }}" class="dropzone"
                                id="deal-dropzone"
                                style="min-height: 150px; border: 2px dashed #dee2e6; border-radius: 8px; background: #f8f9fa;">
                                @csrf
                                <div class="dz-message" data-dz-message>
                                    <div class="text-center py-4">
                                        <i class="ti ti-cloud-upload fs-1 text-muted mb-2"></i>
                                        <p class="mb-0 text-muted">{{ __('Drop files here or click to upload') }}</p>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div id="attachments-list" class="row g-3">
                            @foreach ($deal->attachments as $attachment)
                                <div class="col-md-3 col-sm-4 col-6" id="attachment-{{ $attachment->id }}">
                                    <div class="card attachment-card mb-0 shadow-sm">
                                        @if ($attachment->isImage())
                                            <img src="{{ route('deals.attachment.serve', [$deal->id, $attachment->id]) }}"
                                                class="attachment-preview" alt="{{ $attachment->file_name }}">
                                        @else
                                            <div class="attachment-icon">
                                                <i class="ti ti-file-text"></i>
                                            </div>
                                        @endif
                                        <div class="attachment-info">
                                            <div class="attachment-name" title="{{ $attachment->file_name }}">
                                                {{ $attachment->file_name }}</div>
                                            <div class="attachment-size">{{ $attachment->formatted_size }}</div>
                                        </div>
                                        <div class="attachment-actions">
                                            <a href="{{ route('deals.attachment.download', [$deal->id, $attachment->id]) }}"
                                                class="btn btn-sm btn-light" data-bs-toggle="tooltip"
                                                title="{{ __('Download') }}">
                                                <i class="ti ti-download"></i>
                                            </a>
                                            @can('edit deal')
                                                <button type="button" class="btn btn-sm btn-danger delete-attachment"
                                                    data-id="{{ $attachment->id }}" data-bs-toggle="tooltip"
                                                    title="{{ __('Delete') }}">
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
