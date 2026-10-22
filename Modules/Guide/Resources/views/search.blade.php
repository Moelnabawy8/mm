@extends('layouts.admin')

@section('page-title')
    {{ __('Search Results') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('Guide') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Search') }}</li>
@endsection

@push('css-page')
<style>
    .search-header {
        background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
        border-radius: 15px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
    }
    .search-header h2 {
        font-weight: 700;
        margin-bottom: 15px;
    }
    .search-form {
        max-width: 600px;
    }
    .search-form .form-control {
        border-radius: 50px;
        padding: 15px 25px;
        font-size: 1rem;
        border: none;
    }
    .search-form .btn {
        border-radius: 50px;
        padding: 10px 30px;
        margin-left: -50px;
    }
    .results-count {
        color: #6c757d;
        margin-bottom: 25px;
    }
    .result-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    .result-card:hover {
        transform: translateX(5px);
        border-left-color: #6c5ce7;
    }
    .result-card.video {
        border-left-color: #e17055;
    }
    .result-card.document {
        border-left-color: #00b894;
    }
    .result-type {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        margin-bottom: 10px;
    }
    .result-type.video {
        background: #fff0ed;
        color: #e17055;
    }
    .result-type.document {
        background: #e8f8f5;
        color: #00b894;
    }
    .result-type i {
        margin-right: 6px;
    }
    .result-card h5 {
        font-weight: 600;
        margin-bottom: 10px;
    }
    .result-card p {
        color: #6c757d;
        margin-bottom: 10px;
    }
    .no-results {
        text-align: center;
        padding: 60px;
        background: #f8f9fa;
        border-radius: 15px;
    }
    .no-results i {
        font-size: 64px;
        color: #dee2e6;
        margin-bottom: 20px;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="search-header">
            <h2><i class="ti ti-search me-3"></i>{{ __('Search Results') }}</h2>
            <form action="{{ route('guide.search') }}" method="GET" class="search-form">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" value="{{ $query }}" placeholder="{{ __('Search guides, videos, documentation...') }}">
                    <button type="submit" class="btn btn-light">
                        <i class="ti ti-search"></i> {{ __('Search') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @php
            $totalResults = $videos->count() + $documents->count();
        @endphp
        
        <p class="results-count">
            {{ __('Found') }} <strong>{{ $totalResults }}</strong> {{ __('results for') }} "<strong>{{ $query }}</strong>"
        </p>
        
        @if($totalResults > 0)
            @foreach($videos as $video)
            <a href="{{ route('guide.videos.show', $video->id) }}" class="text-decoration-none">
                <div class="result-card video">
                    <span class="result-type video">
                        <i class="ti ti-video"></i> {{ __('Video') }}
                    </span>
                    <h5>{{ $video->title }}</h5>
                    <p>{{ Str::limit($video->description, 200) }}</p>
                    @if($video->category)
                    <span class="badge" style="background: {{ $video->category->color }};">{{ $video->category->name }}</span>
                    @endif
                </div>
            </a>
            @endforeach
            
            @foreach($documents as $doc)
            <a href="{{ route('guide.documentation.show', $doc->id) }}" class="text-decoration-none">
                <div class="result-card document">
                    <span class="result-type document">
                        <i class="ti ti-file-text"></i> {{ __('Document') }}
                    </span>
                    <h5>{{ $doc->title }}</h5>
                    <p>{{ Str::limit(strip_tags($doc->content), 200) }}</p>
                    @if($doc->category)
                    <span class="badge" style="background: {{ $doc->category->color }};">{{ $doc->category->name }}</span>
                    @endif
                </div>
            </a>
            @endforeach
        @else
            <div class="no-results">
                <i class="ti ti-search-off"></i>
                <h4>{{ __('No Results Found') }}</h4>
                <p class="text-muted">{{ __('Try searching with different keywords or browse our categories.') }}</p>
                <a href="{{ route('guide.index') }}" class="btn btn-primary mt-3">
                    <i class="ti ti-arrow-left me-2"></i> {{ __('Back to Guide') }}
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
