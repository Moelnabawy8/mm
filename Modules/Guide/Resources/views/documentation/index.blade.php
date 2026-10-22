@extends('layouts.admin')

@section('page-title')
    {{ __('Documentation') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('guide.index') }}">{{ __('Guide') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Documentation') }}</li>
@endsection

@push('css-page')
<style>
    .page-header-custom {
        background: linear-gradient(135deg, #00b894 0%, #55efc4 100%);
        border-radius: 15px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
    }
    .page-header-custom h2 {
        font-weight: 700;
        margin-bottom: 10px;
    }
    .doc-sidebar {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        position: sticky;
        top: 100px;
    }
    .doc-sidebar h5 {
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #eee;
    }
    .doc-category {
        margin-bottom: 20px;
    }
    .doc-category-title {
        display: flex;
        align-items: center;
        font-weight: 600;
        color: #2d3436;
        margin-bottom: 10px;
        font-size: 0.95rem;
    }
    .doc-category-title i {
        margin-right: 10px;
        font-size: 18px;
    }
    .doc-category-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .doc-category-list li {
        margin-bottom: 5px;
    }
    .doc-category-list a {
        display: block;
        padding: 8px 15px;
        color: #636e72;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    .doc-category-list a:hover,
    .doc-category-list a.active {
        background: #f8f9fa;
        color: #6c5ce7;
    }
    .doc-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        margin-bottom: 20px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    .doc-card:hover {
        transform: translateX(5px);
        border-left-color: #6c5ce7;
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
    }
    .doc-card p {
        color: #6c757d;
        margin-bottom: 15px;
    }
    .doc-card .badge {
        font-size: 0.8rem;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-header-custom">
            <h2><i class="ti ti-file-text me-3"></i>{{ __('Documentation') }}</h2>
            <p class="mb-0">{{ __('Comprehensive documentation for all ERP modules. Find detailed guides and references.') }}</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3">
        <div class="doc-sidebar">
            <h5><i class="ti ti-list me-2"></i>{{ __('Contents') }}</h5>
            
            @foreach($categories as $category)
            @if($category->documents->count() > 0)
            <div class="doc-category">
                <div class="doc-category-title" style="color: {{ $category->color }};">
                    <i class="{{ $category->icon }}"></i>
                    {{ $category->name }}
                </div>
                <ul class="doc-category-list">
                    @foreach($category->documents as $doc)
                    <li>
                        <a href="{{ route('guide.documentation.show', $doc->id) }}">
                            {{ $doc->title }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    
    <div class="col-lg-9">
        @foreach($categories as $category)
        @if($category->documents->count() > 0)
        <div class="mb-4">
            <h4 class="mb-3" style="color: {{ $category->color }};">
                <i class="{{ $category->icon }} me-2"></i>{{ $category->name }}
            </h4>
            
            @foreach($category->documents as $doc)
            <a href="{{ route('guide.documentation.show', $doc->id) }}" class="text-decoration-none">
                <div class="doc-card">
                    <h5 style="color: {{ $category->color }};">
                        <i class="{{ $doc->icon }}"></i>
                        {{ $doc->title }}
                    </h5>
                    <p>{{ Str::limit(strip_tags($doc->content), 150) }}</p>
                    <span class="badge" style="background: {{ $category->color }};">
                        {{ __('Read More') }} <i class="ti ti-arrow-right ms-1"></i>
                    </span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
        @endforeach
        
        @if($documents->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ti ti-file-off" style="font-size: 64px; color: #dee2e6;"></i>
                <h5 class="mt-3">{{ __('No Documentation Available') }}</h5>
                <p class="text-muted">{{ __('Documentation will appear here once it is added to the system.') }}</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
