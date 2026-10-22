@extends('layouts.admin')

@section('page-title')
    {{ $video->title }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('Guide') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.videos') }}">{{ __('Videos') }}</a></li>
    <li class="breadcrumb-item active">{{ Str::limit($video->title, 30) }}</li>
@endsection

@push('css-page')
    <style>
        .video-player-container {
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .video-player {
            width: 100%;
            max-height: 70vh;
            display: block;
        }

        .video-info {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .video-info h2 {
            font-weight: 700;
            margin-bottom: 15px;
        }

        .video-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .video-meta span {
            display: flex;
            align-items: center;
            color: #6c757d;
        }

        .video-meta span i {
            margin-right: 8px;
            font-size: 18px;
        }

        .video-description {
            color: #495057;
            line-height: 1.8;
            font-size: 1.05rem;
        }

        .related-videos {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
        }

        .related-videos h5 {
            font-weight: 600;
            margin-bottom: 20px;
        }

        .related-video-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .related-video-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .related-video-thumb {
            width: 120px;
            height: 70px;
            background: linear-gradient(135deg, #2d3436 0%, #636e72 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .related-video-thumb i {
            color: white;
            font-size: 24px;
        }

        .related-video-info h6 {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }

        .related-video-info p {
            color: #6c757d;
            font-size: 0.8rem;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 25px;
            color: white;
            font-size: 0.9rem;
        }

        .category-badge i {
            margin-right: 8px;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="video-player-container">
                <video class="video-player" controls autoplay>
                    <source src="{{ str_replace(' ', '%20', asset($video->video_path)) }}" type="video/mp4">
                    {{ __('Your browser does not support the video tag.') }}
                </video>
            </div>

            <div class="video-info">
                <h2>{{ $video->title }}</h2>
                <div class="video-meta">
                    <span class="category-badge" style="background: {{ $video->category->color }};">
                        <i class="{{ $video->category->icon }}"></i> {{ $video->category->name }}
                    </span>
                    <p class="text-danger mt-2">Debug URL: {{ str_replace(' ', '%20', asset($video->video_path)) }}</p>
                    @if ($video->duration)
                        <span><i class="ti ti-clock"></i> {{ $video->duration }}</span>
                    @endif
                    <span><i class="ti ti-calendar"></i> {{ $video->created_at->format('M d, Y') }}</span>
                </div>
                <div class="video-description">
                    {!! nl2br(e($video->description)) !!}
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="related-videos">
                <h5><i class="ti ti-playlist me-2"></i>{{ __('Related Videos') }}</h5>

                @forelse($relatedVideos as $related)
                    <a href="{{ route('guide.videos.show', $related->id) }}" class="related-video-item">
                        <div class="related-video-thumb">
                            <i class="ti ti-player-play"></i>
                        </div>
                        <div class="related-video-info">
                            <h6>{{ $related->title }}</h6>
                            <p>{{ $related->description }}</p>
                        </div>
                    </a>
                @empty
                    <div class="text-center text-muted py-3">
                        <i class="ti ti-video-off" style="font-size: 32px;"></i>
                        <p class="mt-2 mb-0">{{ __('No related videos') }}</p>
                    </div>
                @endforelse

                <a href="{{ route('guide.videos') }}" class="btn btn-primary w-100 mt-3">
                    <i class="ti ti-video me-2"></i> {{ __('Browse All Videos') }}
                </a>
            </div>

            @if ($video->category)
                <div class="related-videos mt-4">
                    <h5><i class="ti ti-folder me-2"></i>{{ __('Category') }}</h5>
                    <a href="{{ route('guide.category', $video->category->slug) }}" class="related-video-item">
                        <div class="related-video-thumb" style="background: {{ $video->category->color }};">
                            <i class="{{ $video->category->icon }}"></i>
                        </div>
                        <div class="related-video-info">
                            <h6>{{ $video->category->name }}</h6>
                            <p>{{ $video->category->description }}</p>
                        </div>
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const video = document.querySelector('.video-player');
            video.volume = 0.7;
        });
    </script>
@endpush
