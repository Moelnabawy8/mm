@extends('layouts.admin')

@section('page-title')
    {{ __('Edit Post') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.posts') }}">{{ __('Posts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Edit Post') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('marketing.posts.update', $post->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            @if($post->status === 'published')
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <i class="ti ti-info-circle"></i> {{ __('This post is already published on Meta. You can only edit the caption. Media and scheduling cannot be changed.') }}
                                </div>
                            </div>
                            @endif

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Account') }}</label>
                                <input type="text" class="form-control" value="{{ $post->account->account_name }} ({{ ucfirst($post->account->platform) }})" readonly>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Content') }}</label>
                                <textarea name="content" class="form-control" rows="6" required>{{ old('content', $post->content) }}</textarea>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Media Type') }}</label>
                                <input type="text" class="form-control" value="{{ ucfirst($post->media_type) }}" readonly>
                                <input type="hidden" name="media_type" value="{{ $post->media_type }}">
                            </div>

                            @if($post->media_type !== 'carousel')
                            <div class="col-md-12 mb-3" id="single_media_input">
                                <label class="form-label">{{ __('Media URL') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                <input type="url" name="media_url" class="form-control" value="{{ old('media_url', $post->media_url) }}" placeholder="https://example.com/image.jpg OR Google Drive Link" {{ $post->status === 'published' ? 'readonly' : '' }}>
                                <small class="text-muted">{{ __('Direct link or a Google Drive link to image/video') }}</small>
                            </div>
                            @else
                            <div class="col-md-12 mb-3" id="carousel_media_input">
                                <label class="form-label">{{ __('Carousel Images (Max 10)') }}</label>
                                <div id="carousel_urls_container">
                                    @foreach($post->metadata['urls'] ?? [] as $url)
                                    <div class="input-group mb-2">
                                        <input type="url" name="media_urls[]" class="form-control" value="{{ $url }}" {{ $post->status === 'published' ? 'readonly' : '' }}>
                                        @if($post->status !== 'published')
                                        <button type="button" class="btn btn-outline-danger remove-url"><i class="ti ti-trash"></i></button>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                                @if($post->status !== 'published')
                                <button type="button" class="btn btn-sm btn-outline-success" id="add_more_urls">
                                    <i class="ti ti-plus me-1"></i>{{ __('Add Another Image') }}
                                </button>
                                @endif
                                <small class="text-muted d-block mt-1">{{ __('You can paste direct links or Google Drive links') }}</small>
                            </div>
                            @endif

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Schedule Post') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at', $post->scheduled_at ? $post->scheduled_at->format('Y-m-d\TH:i') : '') }}" {{ $post->status === 'published' ? 'readonly' : '' }}>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <a href="{{ route('marketing.posts') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addBtn = document.getElementById('add_more_urls');
        const container = document.getElementById('carousel_urls_container');

        if(addBtn && container) {
            addBtn.addEventListener('click', function() {
                const urlCount = container.querySelectorAll('.input-group').length;
                if(urlCount >= 10) {
                    alert('Maximum 10 images allowed for carousel.');
                    return;
                }
                
                const group = document.createElement('div');
                group.className = 'input-group mb-2';
                group.innerHTML = `
                    <input type="url" name="media_urls[]" class="form-control" placeholder="https://example.com/slide${urlCount+1}.jpg OR Google Drive Link">
                    <button type="button" class="btn btn-outline-danger remove-url"><i class="ti ti-trash"></i></button>
                `;
                container.appendChild(group);
            });

            container.addEventListener('click', function(e) {
                if(e.target.closest('.remove-url')) {
                    e.target.closest('.input-group').remove();
                }
            });
        }
    });
</script>
@endpush
