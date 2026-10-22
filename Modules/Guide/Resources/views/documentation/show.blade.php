@extends('layouts.admin')

@section('page-title')
    {{ $document->title }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('Guide') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.documentation') }}">{{ __('Documentation') }}</a></li>
    <li class="breadcrumb-item active">{{ Str::limit($document->title, 30) }}</li>
@endsection

@push('css-page')
<style>
    .doc-container {
        background: white;
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    .doc-header {
        margin-bottom: 30px;
        padding-bottom: 25px;
        border-bottom: 2px solid #eee;
    }
    .doc-header h1 {
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    .doc-header h1 i {
        margin-right: 15px;
        font-size: 36px;
    }
    .doc-meta {
        display: flex;
        gap: 20px;
        color: #6c757d;
    }
    .doc-meta span {
        display: flex;
        align-items: center;
    }
    .doc-meta span i {
        margin-right: 8px;
    }
    .doc-content {
        line-height: 1.8;
        font-size: 1.05rem;
        color: #2d3436;
    }
    .doc-content h2 {
        font-weight: 700;
        font-size: 1.5rem;
        margin-top: 35px;
        margin-bottom: 20px;
        color: #2d3436;
        padding-bottom: 10px;
        border-bottom: 2px solid #eee;
    }
    .doc-content h3 {
        font-weight: 600;
        font-size: 1.25rem;
        margin-top: 25px;
        margin-bottom: 15px;
        color: #636e72;
    }
    .doc-content ul, .doc-content ol {
        margin: 20px 0;
        padding-left: 25px;
    }
    .doc-content li {
        margin-bottom: 10px;
    }
    .doc-content strong {
        color: #2d3436;
    }
    .doc-content p {
        margin-bottom: 15px;
    }
    .related-docs {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 25px;
    }
    .related-docs h5 {
        font-weight: 600;
        margin-bottom: 20px;
    }
    .related-doc-item {
        display: flex;
        align-items: center;
        padding: 15px;
        background: white;
        border-radius: 10px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
    }
    .related-doc-item:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .related-doc-item i {
        font-size: 24px;
        margin-right: 15px;
        color: #6c5ce7;
    }
    .related-doc-item h6 {
        margin: 0;
        font-weight: 600;
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
    .toc-sidebar {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        position: sticky;
        top: 100px;
    }
    .toc-sidebar h5 {
        font-weight: 600;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #eee;
    }
    .toc-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .toc-list li {
        margin-bottom: 8px;
    }
    .toc-list a {
        color: #636e72;
        text-decoration: none;
        font-size: 0.9rem;
        display: block;
        padding: 5px 0;
        transition: all 0.2s ease;
    }
    .toc-list a:hover {
        color: #6c5ce7;
        padding-left: 10px;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="doc-container">
            <div class="doc-header">
                <h1 style="color: {{ $document->category->color ?? '#6c5ce7' }};">
                    <i class="{{ $document->icon }}"></i>
                    {{ $document->title }}
                </h1>
                <div class="doc-meta">
                    @if($document->category)
                    <span class="category-badge" style="background: {{ $document->category->color }};">
                        <i class="{{ $document->category->icon }}"></i> {{ $document->category->name }}
                    </span>
                    @endif
                    <span><i class="ti ti-calendar"></i> {{ __('Updated') }}: {{ $document->updated_at->format('M d, Y') }}</span>
                </div>
            </div>
            
            <div class="doc-content">
                {!! $document->content !!}
            </div>
        </div>
        
        <div class="d-flex justify-content-between">
            <a href="{{ route('guide.documentation') }}" class="btn btn-outline-primary">
                <i class="ti ti-arrow-left me-2"></i> {{ __('Back to Documentation') }}
            </a>
            @if($document->category)
            <a href="{{ route('guide.category', $document->category->slug) }}" class="btn btn-primary">
                {{ __('More in') }} {{ $document->category->name }} <i class="ti ti-arrow-right ms-2"></i>
            </a>
            @endif
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="related-docs mb-4">
            <h5><i class="ti ti-files me-2"></i>{{ __('Related Documents') }}</h5>
            
            @forelse($relatedDocs as $related)
            <a href="{{ route('guide.documentation.show', $related->id) }}" class="related-doc-item">
                <i class="{{ $related->icon }}"></i>
                <h6>{{ $related->title }}</h6>
            </a>
            @empty
            <div class="text-center text-muted py-3">
                <i class="ti ti-file-off" style="font-size: 32px;"></i>
                <p class="mt-2 mb-0">{{ __('No related documents') }}</p>
            </div>
            @endforelse
        </div>
        
        @if($document->category)
        <div class="related-docs">
            <h5><i class="ti ti-folder me-2"></i>{{ __('Category') }}</h5>
            <a href="{{ route('guide.category', $document->category->slug) }}" class="related-doc-item">
                <i class="{{ $document->category->icon }}" style="color: {{ $document->category->color }};"></i>
                <div>
                    <h6>{{ $document->category->name }}</h6>
                    <small class="text-muted">{{ $document->category->description }}</small>
                </div>
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
