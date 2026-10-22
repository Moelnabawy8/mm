@extends('layouts.admin')
@section('page-title')
    {{ __('Video Streaming') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item">{{ __('Video Streaming') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('My Videos') }}</h5>
                        <a href="{{ route('videostreaming.create') }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>{{ __('Upload Video') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if ($videos->isEmpty())
                        <div class="text-center py-5">
                            <i class="ti ti-video-off" style="font-size: 4rem; color: #ccc;"></i>
                            <h5 class="mt-3">{{ __('No videos yet') }}</h5>
                            <p class="text-muted">{{ __('Upload your first video to get started') }}</p>
                            <a href="{{ route('videostreaming.create') }}" class="btn btn-primary">
                                <i class="ti ti-upload me-1"></i>{{ __('Upload Video') }}
                            </a>
                        </div>
                    @else
                        <div class="row">
                            @foreach ($videos as $video)
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                                    <div class="card video-card h-100">
                                        <div class="position-relative">
                                            @if ($video->thumbnail_path)
                                                <img src="{{ asset($video->thumbnail_path) }}" class="card-img-top"
                                                    alt="{{ $video->title }}" style="height: 160px; object-fit: cover;">
                                            @else
                                                <div class="bg-secondary d-flex align-items-center justify-content-center"
                                                    style="height: 160px;">
                                                    <i class="ti ti-video" style="font-size: 3rem; color: #fff;"></i>
                                                </div>
                                            @endif

                                            @php
                                                $duration = '00:00';
                                                if (
                                                    is_object($video) &&
                                                    method_exists($video, 'getFormattedDurationAttribute')
                                                ) {
                                                    $duration = $video->getFormattedDurationAttribute();
                                                } elseif (isset($video->duration)) {
                                                    $seconds = $video->duration;
                                                    $hours = floor($seconds / 3600);
                                                    $minutes = floor(($seconds % 3600) / 60);
                                                    $secs = $seconds % 60;
                                                    $duration =
                                                        $hours > 0
                                                            ? sprintf('%02d:%02d:%02d', $hours, $minutes, $secs)
                                                            : sprintf('%02d:%02d', $minutes, $secs);
                                                }
                                            @endphp
                                            <span
                                                class="position-absolute bottom-0 end-0 bg-dark text-white px-2 py-1 m-2 rounded small">
                                                {{ $duration }}
                                            </span>

                                            @if ($video->status !== 'ready')
                                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
                                                    style="background: rgba(0,0,0,0.7);">
                                                    @if ($video->status === 'processing')
                                                        <div class="text-center text-white">
                                                            <div class="spinner-border spinner-border-sm" role="status">
                                                            </div>
                                                            <div class="small mt-1">{{ __('Processing...') }}</div>
                                                        </div>
                                                    @elseif($video->status === 'failed')
                                                        <div class="text-center text-danger">
                                                            <i class="ti ti-alert-circle" style="font-size: 2rem;"></i>
                                                            <div class="small mt-1">{{ __('Failed') }}</div>
                                                        </div>
                                                    @else
                                                        <div class="text-center text-white">
                                                            <div class="spinner-border spinner-border-sm" role="status">
                                                            </div>
                                                            <div class="small mt-1">{{ __('Uploading...') }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <div class="card-body p-3">
                                            <h6 class="card-title mb-1 text-truncate" title="{{ $video->title }}">
                                                @if (!empty($video->password_protected))
                                                    <i class="ti ti-lock text-warning me-1"
                                                        title="{{ __('Password Protected') }}"></i>
                                                @endif
                                                @if (!empty($video->expires_at) && \Carbon\Carbon::parse($video->expires_at)->isPast())
                                                    <i class="ti ti-clock-off text-danger me-1"
                                                        title="{{ __('Expired') }}"></i>
                                                @endif
                                                {{ $video->title }}
                                            </h6>
                                            <p class="text-muted small mb-2">
                                                {{ \Carbon\Carbon::parse($video->created_at)->diffForHumans() }}
                                                @if (!empty($video->max_views))
                                                    • {{ $video->views_count ?? 0 }}/{{ $video->max_views }}
                                                    {{ __('views') }}
                                                @endif
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                @php
                                                    $isExpired =
                                                        !empty($video->expires_at) &&
                                                        \Carbon\Carbon::parse($video->expires_at)->isPast();
                                                @endphp
                                                <span
                                                    class="badge bg-{{ $video->status === 'ready' ? ($isExpired ? 'danger' : 'success') : ($video->status === 'failed' ? 'danger' : 'warning') }}">
                                                    {{ $isExpired ? __('Expired') : ucfirst($video->status) }}
                                                </span>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-link text-dark p-0"
                                                        data-bs-toggle="dropdown">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item"
                                                                href="{{ route('videostreaming.show', $video->uuid) }}">
                                                                <i class="ti ti-eye me-2"></i>{{ __('View Details') }}
                                                            </a>
                                                        </li>
                                                        @if ($video->status === 'ready')
                                                            <li>
                                                                <a class="dropdown-item copy-embed" href="#"
                                                                    data-uuid="{{ $video->uuid }}">
                                                                    <i
                                                                        class="ti ti-code me-2"></i>{{ __('Copy Embed Code') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                        <li>
                                                            <hr class="dropdown-divider">
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger delete-video" href="#"
                                                                data-uuid="{{ $video->uuid }}">
                                                                <i class="ti ti-trash me-2"></i>{{ __('Delete') }}
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (method_exists($videos, 'links'))
                            <div class="d-flex justify-content-center mt-4">
                                {{ $videos->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Copy embed code
            document.querySelectorAll('.copy-embed').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const uuid = this.dataset.uuid;

                    fetch(`{{ url('video-streaming/video') }}/${uuid}/embed-code`)
                        .then(res => res.json())
                        .then(data => {
                            navigator.clipboard.writeText(data.iframe_code).then(() => {
                                toastr.success(
                                    '{{ __('Embed code copied to clipboard') }}');
                            });
                        });
                });
            });

            // Delete video
            document.querySelectorAll('.delete-video').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const uuid = this.dataset.uuid;

                    if (confirm('{{ __('Are you sure you want to delete this video?') }}')) {
                        fetch(`{{ url('video-streaming/video') }}/${uuid}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    location.reload();
                                } else {
                                    toastr.error(data.message);
                                }
                            });
                    }
                });
            });
        });
    </script>
@endpush
