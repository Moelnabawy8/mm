@extends('layouts.admin')

@section('page-title')
    {{ __('Video Tutorials') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('Guide') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Videos') }}</li>
@endsection

@push('css-page')
    <style>
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .video-card {
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: 100%;
        }

        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .video-thumbnail {
            position: relative;
            padding-top: 56.25%;
            background: linear-gradient(135deg, #2d3436 0%, #636e72 100%);
            background-size: cover;
            background-position: center;
        }

        .video-thumbnail video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-thumbnail .play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #6c5ce7;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .video-card:hover .play-btn {
            background: #6c5ce7;
            color: white;
            transform: translate(-50%, -50%) scale(1.1);
        }

        .video-card .card-body {
            padding: 20px;
        }

        .video-card .card-body h5 {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .video-card .card-body p {
            color: #6c757d;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .filter-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .category-filter .btn {
            border-radius: 25px;
            padding: 8px 20px;
            font-size: 0.9rem;
        }

        .category-filter .btn.active {
            background: #6c5ce7;
            border-color: #6c5ce7;
        }

        .page-header-custom {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .page-header-custom h2 {
            font-weight: 700;
            margin-bottom: 10px;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-header-custom">
                <h2><i class="ti ti-video me-3"></i>{{ __('Video Tutorials') }}</h2>
                <p class="mb-0">{{ __('Watch comprehensive video tutorials to master every aspect of the ERP system.') }}
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="filter-section">
                <div class="category-filter">
                    <a href="{{ route('guide.videos') }}"
                        class="btn btn-outline-primary {{ !request('category') ? 'active' : '' }}">
                        {{ __('All Videos') }}
                    </a>
                    @foreach ($categories as $category)
                        <a href="{{ route('guide.videos', ['category' => $category->slug]) }}"
                            class="btn btn-outline-primary {{ request('category') == $category->slug ? 'active' : '' }}"
                            style="{{ request('category') == $category->slug ? 'background:' . $category->color . ';border-color:' . $category->color : '' }}">
                            <i class="{{ $category->icon }} me-1"></i> {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @forelse($videos as $video)
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="{{ route('guide.videos.show', $video->id) }}" class="text-decoration-none">
                    <div class="card video-card">
                        <div class="video-thumbnail">
                            <video muted preload="metadata">
                                <source src="{{ asset($video->video_path) }}#t=0.5" type="video/mp4">
                            </video>
                            <div class="play-btn">
                                <i class="ti ti-player-play"></i>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5>{{ $video->title }}</h5>
                            <p>{{ $video->description }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                @if ($video->category)
                                    <span class="badge" style="background: {{ $video->category->color }};">
                                        <i class="{{ $video->category->icon }} me-1"></i> {{ $video->category->name }}
                                    </span>
                                @endif
                                @if ($video->duration)
                                    <span class="text-muted small">
                                        <i class="ti ti-clock me-1"></i> {{ $video->duration }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-video-off" style="font-size: 64px; color: #dee2e6;"></i>
                        <h5 class="mt-3">{{ __('No Videos Available') }}</h5>
                        <p class="text-muted">
                            {{ __('Video tutorials will appear here once they are added to the system.') }}</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
