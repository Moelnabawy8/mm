@extends('layouts.admin')

@section('page-title')
    {{ $category->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('Guide') }}</a></li>
    <li class="breadcrumb-item active">{{ $category->name }}</li>
@endsection

@push('css-page')
    <style>
        .category-hero {
            background: linear-gradient(135deg, {{ $category->color }} 0%, {{ $category->color }}99 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
        }

        .category-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .category-hero h1 i {
            font-size: 3rem;
            margin-right: 20px;
        }

        .category-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .category-stats {
            display: flex;
            gap: 30px;
        }

        .category-stats .stat {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 25px;
        }

        .category-stats .stat i {
            font-size: 20px;
            margin-right: 10px;
        }

        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title h4 {
            font-weight: 700;
            margin: 0;
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
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: {{ $category->color }};
            transition: all 0.3s ease;
        }

        .video-card:hover .play-btn {
            background: {{ $category->color }};
            color: white;
            transform: translate(-50%, -50%) scale(1.1);
        }

        .doc-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 15px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .doc-card:hover {
            transform: translateX(5px);
            border-left-color: {{ $category->color }};
        }

        .doc-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .doc-card h5 i {
            margin-right: 12px;
            font-size: 22px;
            color: {{ $category->color }};
        }

        .doc-card p {
            color: #6c757d;
            margin-bottom: 0;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="category-hero">
                <h1><i class="{{ $category->icon }}"></i> {{ $category->name }}</h1>
                <p>{{ $category->description }}</p>
                <div class="category-stats">
                    <div class="stat">
                        <i class="ti ti-video"></i>
                        <span>{{ $videos->count() }} {{ __('Videos') }}</span>
                    </div>
                    <div class="stat">
                        <i class="ti ti-file-text"></i>
                        <span>{{ $documents->count() }} {{ __('Documents') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($videos->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="section-title">
                    <h4><i class="ti ti-video me-2" style="color: {{ $category->color }};"></i>{{ __('Video Tutorials') }}
                    </h4>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            @foreach ($videos as $video)
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
                                <h6 class="fw-bold mb-2">{{ $video->title }}</h6>
                                <p class="text-muted small mb-0"
                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    {{ $video->description }}
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($documents->count() > 0)
        <div class="row mb-4">
            <div class="col-12">
                <div class="section-title">
                    <h4><i class="ti ti-file-text me-2"
                            style="color: {{ $category->color }};"></i>{{ __('Documentation') }}</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                @foreach ($documents as $doc)
                    <a href="{{ route('guide.documentation.show', $doc->id) }}" class="text-decoration-none">
                        <div class="doc-card">
                            <h5><i class="{{ $doc->icon }}"></i> {{ $doc->title }}</h5>
                            <p>{{ Str::limit(strip_tags($doc->content), 200) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($videos->count() == 0 && $documents->count() == 0)
        <div class="row">
            <div class="col-12">
                <div class="empty-state">
                    <i class="ti ti-folder-off"></i>
                    <h5>{{ __('No Content Available') }}</h5>
                    <p class="text-muted">{{ __('Content for this category will be added soon.') }}</p>
                    <a href="{{ route('guide.index') }}" class="btn btn-primary mt-3">
                        <i class="ti ti-arrow-left me-2"></i> {{ __('Back to Guide') }}
                    </a>
                </div>
            </div>
        </div>
    @endif
@endsection
