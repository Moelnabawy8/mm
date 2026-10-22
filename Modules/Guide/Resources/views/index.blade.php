@extends('layouts.admin')

@section('page-title')
    {{ __('System Guide') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Guide') }}</li>
@endsection

@push('css-page')
    <style>
        .guide-hero {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
        }

        .guide-hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .guide-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .guide-search {
            max-width: 500px;
            margin-top: 25px;
        }

        .guide-search .form-control {
            border-radius: 50px;
            padding: 15px 25px;
            font-size: 1rem;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .guide-search .btn {
            border-radius: 50px;
            padding: 10px 30px;
            margin-left: -50px;
        }

        .category-card {
            border-radius: 15px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: 100%;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .category-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 20px;
        }

        .category-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
        }

        .category-card p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .category-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .category-stats span {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .category-stats i {
            margin-right: 5px;
        }

        .video-card {
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
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
            color: #6c5ce7;
            transition: all 0.3s ease;
        }

        .video-card:hover .play-btn {
            background: #6c5ce7;
            color: white;
            transform: translate(-50%, -50%) scale(1.1);
        }

        .video-card .card-body h6 {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .video-card .card-body p {
            color: #6c757d;
            font-size: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .section-title h4 {
            font-weight: 700;
            margin: 0;
        }

        .section-title a {
            color: #6c5ce7;
            font-weight: 500;
        }

        .quick-links {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
        }

        .quick-link-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .quick-link-item:hover {
            background: #6c5ce7;
            color: white;
        }

        .quick-link-item i {
            font-size: 20px;
            margin-right: 15px;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="guide-hero">
                <h1><i class="ti ti-book-2 me-3"></i>{{ __('ERP System Guide') }}</h1>
                <p>{{ __('Your complete resource for learning and mastering the ERP system. Watch video tutorials, read documentation, and become an expert.') }}
                </p>
                <form action="{{ route('guide.search') }}" method="GET" class="guide-search">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control"
                            placeholder="{{ __('Search guides, videos, documentation...') }}">
                        <button type="submit" class="btn btn-light">
                            <i class="ti ti-search"></i> {{ __('Search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="section-title">
                <h4><i class="ti ti-category me-2"></i>{{ __('Browse by Category') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        @forelse($categories as $category)
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="{{ route('guide.category', $category->slug) }}" class="text-decoration-none">
                    <div class="card category-card">
                        <div class="card-body p-4">
                            <div class="category-icon" style="background: {{ $category->color }};">
                                <i class="{{ $category->icon }}"></i>
                            </div>
                            <h5>{{ $category->name }}</h5>
                            <p>{{ $category->description }}</p>
                            <div class="category-stats">
                                <span><i class="ti ti-video"></i> {{ $category->videos->count() }}
                                    {{ __('Videos') }}</span>
                                <span><i class="ti ti-file-text"></i> {{ $category->documents->count() }}
                                    {{ __('Docs') }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">
                    {{ __('No categories available yet. Please run the seeder to populate content.') }}
                </div>
            </div>
        @endforelse
    </div>

    <!-- Latest Videos Section -->
    <div class="row mt-4 mb-4">
        <div class="col-12">
            <div class="section-title">
                <h4><i class="ti ti-video me-2"></i>{{ __('Video Tutorials') }}</h4>
                <a href="{{ route('guide.videos') }}">{{ __('View All') }} <i class="ti ti-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        @forelse($videos->take(6) as $video)
            <div class="col-lg-4 col-md-6 mb-4">
                <a href="{{ route('guide.videos.show', $video->id) }}" class="text-decoration-none">
                    <div class="card video-card">
                        <div class="video-thumbnail">
                            <video muted preload="metadata">
                                <source src="{{ str_replace(' ', '%20', asset($video->video_path)) }}#t=0.5"
                                    type="video/mp4">
                            </video>
                            <div class="play-btn">
                                <i class="ti ti-player-play"></i>
                            </div>
                        </div>
                        <div class="card-body">
                            <h6>{{ $video->title }}</h6>
                            <p>{{ $video->description }}</p>
                            @if ($video->category)
                                <span class="badge"
                                    style="background: {{ $video->category->color }};">{{ $video->category->name }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">
                    {{ __('No videos available yet.') }}
                </div>
            </div>
        @endforelse
    </div>

    <!-- Documentation Section -->
    <div class="row mt-4 mb-4">
        <div class="col-12">
            <div class="section-title">
                <h4><i class="ti ti-file-text me-2"></i>{{ __('Documentation') }}</h4>
                <a href="{{ route('guide.documentation') }}">{{ __('View All') }} <i class="ti ti-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="row">
                @forelse($documents->take(4) as $doc)
                    <div class="col-md-6 mb-4">
                        <a href="{{ route('guide.documentation.show', $doc->id) }}" class="text-decoration-none">
                            <div class="card category-card">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="category-icon me-3"
                                            style="background: {{ $doc->category->color ?? '#6c5ce7' }}; width: 50px; height: 50px; font-size: 20px;">
                                            <i class="{{ $doc->icon }}"></i>
                                        </div>
                                        <h6 class="mb-0">{{ $doc->title }}</h6>
                                    </div>
                                    @if ($doc->category)
                                        <span class="badge"
                                            style="background: {{ $doc->category->color }};">{{ $doc->category->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info">
                            {{ __('No documentation available yet.') }}
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
        <div class="col-lg-4">
            <div class="quick-links">
                <h5 class="mb-4"><i class="ti ti-bolt me-2"></i>{{ __('Quick Links') }}</h5>
                <a href="{{ route('guide.videos') }}" class="quick-link-item">
                    <i class="ti ti-video"></i>
                    <span>{{ __('All Video Tutorials') }}</span>
                </a>
                <a href="{{ route('guide.documentation') }}" class="quick-link-item">
                    <i class="ti ti-file-text"></i>
                    <span>{{ __('Full Documentation') }}</span>
                </a>
                @foreach ($categories->take(3) as $cat)
                    <a href="{{ route('guide.category', $cat->slug) }}" class="quick-link-item">
                        <i class="{{ $cat->icon }}"></i>
                        <span>{{ $cat->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endsection
