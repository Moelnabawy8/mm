@extends('layouts.admin')
@section('page-title')
    {{ $video->title }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('videostreaming.index') }}">{{ __('Video Streaming') }}</a></li>
    <li class="breadcrumb-item">{{ $video->title }}</li>
@endsection

@php
    $isExpired = !empty($video->expires_at) && \Carbon\Carbon::parse($video->expires_at)->isPast();
    $embedUrl = route('video.embed', $video->uuid);
    $iframeCode = '<iframe src="' . $embedUrl . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>';
@endphp

@section('content')
<div class="row">
    <!-- Video Preview -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-0">
                @if($video->status === 'ready')
                    <div class="ratio ratio-16x9">
                        <iframe src="{{ $embedUrl }}" 
                                frameborder="0" 
                                allowfullscreen>
                        </iframe>
                    </div>
                @elseif($video->status === 'processing')
                    <div class="bg-dark d-flex align-items-center justify-content-center" style="height: 400px;">
                        <div class="text-center text-white">
                            <div class="spinner-border mb-3" role="status"></div>
                            <h5>{{ __('Video is being processed...') }}</h5>
                        </div>
                    </div>
                @elseif($video->status === 'failed')
                    <div class="bg-dark d-flex align-items-center justify-content-center" style="height: 400px;">
                        <div class="text-center text-danger">
                            <i class="ti ti-alert-circle" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">{{ __('Processing Failed') }}</h5>
                        </div>
                    </div>
                @else
                    <div class="bg-dark d-flex align-items-center justify-content-center" style="height: 400px;">
                        <div class="text-center text-white">
                            <div class="spinner-border mb-3" role="status"></div>
                            <h5>{{ __('Uploading...') }}</h5>
                        </div>
                    </div>
                @endif
            </div>
            <div class="card-footer">
                <h5 class="mb-2">{{ $video->title }}</h5>
                <p class="text-muted mb-0">{{ $video->description ?? __('No description') }}</p>
            </div>
        </div>

        <!-- Embed Code -->
        @if($video->status === 'ready')
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Embed Code') }}</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Embed URL') }}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="{{ $embedUrl }}" readonly id="embedUrl">
                        <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('embedUrl')">
                            <i class="ti ti-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('iFrame Code') }}</label>
                    <div class="input-group">
                        <textarea class="form-control" rows="3" readonly id="iframeCode">{{ $iframeCode }}</textarea>
                        <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('iframeCode')">
                            <i class="ti ti-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Video Info Sidebar -->
    <div class="col-lg-4">
        <!-- Status Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Video Status') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge bg-{{ $video->status === 'ready' ? 'success' : ($video->status === 'failed' ? 'danger' : 'warning') }} me-2" style="font-size: 1rem;">
                        {{ ucfirst($video->status) }}
                    </span>
                </div>
                
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <strong>{{ __('Uploaded') }}:</strong> {{ \Carbon\Carbon::parse($video->created_at)->format('M d, Y H:i') }}
                    </li>
                    <li class="mb-2">
                        <strong>{{ __('Views') }}:</strong> {{ $video->views_count ?? 0 }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Protection Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Protection Settings') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Password Protected') }}</span>
                    <span class="badge bg-{{ !empty($video->password_protected) ? 'warning' : 'secondary' }}">
                        {{ !empty($video->password_protected) ? __('Yes') : __('No') }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Domain Lock') }}</span>
                    <span class="badge bg-{{ !empty($video->domain_lock_enabled) ? 'success' : 'secondary' }}">
                        {{ !empty($video->domain_lock_enabled) ? __('Enabled') : __('Disabled') }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Watermark') }}</span>
                    <span class="badge bg-{{ !empty($video->watermark_enabled) ? 'success' : 'secondary' }}">
                        {{ !empty($video->watermark_enabled) ? __('Enabled') : __('Disabled') }}
                    </span>
                </div>
                @if(!empty($video->max_views))
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Max Views') }}</span>
                    <span class="badge bg-info">{{ $video->views_count ?? 0 }}/{{ $video->max_views }}</span>
                </div>
                @endif
                @if(!empty($video->expires_at))
                <div class="d-flex justify-content-between align-items-center">
                    <span>{{ __('Expires') }}</span>
                    <span class="badge bg-{{ $isExpired ? 'danger' : 'info' }}">
                        {{ \Carbon\Carbon::parse($video->expires_at)->format('M d, Y H:i') }}
                    </span>
                </div>
                @endif
            </div>
        </div>

        <!-- Player Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Player Settings') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Auto Play') }}</span>
                    <span class="badge bg-{{ !empty($video->auto_play) ? 'success' : 'secondary' }}">
                        {{ !empty($video->auto_play) ? __('Yes') : __('No') }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Loop Video') }}</span>
                    <span class="badge bg-{{ !empty($video->loop_video) ? 'success' : 'secondary' }}">
                        {{ !empty($video->loop_video) ? __('Yes') : __('No') }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>{{ __('Show Controls') }}</span>
                    <span class="badge bg-{{ !empty($video->show_controls) ? 'success' : 'secondary' }}">
                        {{ !empty($video->show_controls) ? __('Yes') : __('No') }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>{{ __('Allow Download') }}</span>
                    <span class="badge bg-{{ !empty($video->download_enabled) ? 'warning' : 'secondary' }}">
                        {{ !empty($video->download_enabled) ? __('Yes') : __('No') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-body">
                <a href="{{ route('videostreaming.index') }}" class="btn btn-secondary w-100 mb-2">
                    <i class="ti ti-arrow-left me-1"></i>{{ __('Back to Videos') }}
                </a>
                <button type="button" class="btn btn-danger w-100" onclick="deleteVideo('{{ $video->uuid }}')">
                    <i class="ti ti-trash me-1"></i>{{ __('Delete Video') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    document.execCommand('copy');
    toastr.success('{{ __("Copied to clipboard") }}');
}

function deleteVideo(uuid) {
    if (confirm('{{ __("Are you sure you want to delete this video?") }}')) {
        fetch('{{ url("video-streaming/video") }}/' + uuid, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '{{ route("videostreaming.index") }}';
            }
        });
    }
}
</script>
@endpush
