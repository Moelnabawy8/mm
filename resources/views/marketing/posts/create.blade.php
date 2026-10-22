@extends('layouts.admin')

@section('page-title')
    {{ __('Create Post') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.posts') }}">{{ __('Posts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Create New Post') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('marketing.posts.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Select Account') }}</label>
                                <select name="account_id" class="form-control" required>
                                    <option value="">{{ __('Choose an account') }}</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}">
                                            {{ $account->account_name }} ({{ ucfirst($account->platform) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Campaign') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                <select name="campaign_id" class="form-control">
                                    <option value="">{{ __('No campaign') }}</option>
                                    @foreach($campaigns as $campaign)
                                        <option value="{{ $campaign->id }}">
                                            {{ $campaign->campaign_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Content') }}</label>
                                <textarea name="content" class="form-control" rows="6" placeholder="{{ __('Write your post content here...') }}" required></textarea>
                                <small class="text-muted">{{ __('Tip: Keep it engaging and include relevant hashtags') }}</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Media Type') }}</label>
                                <select name="media_type" class="form-control" id="media_type">
                                    <option value="image">{{ __('Photo / Image') }}</option>
                                    <option value="video">{{ __('Video / Reel') }}</option>
                                    <option value="carousel">{{ __('Carousel (Multi-Image)') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3" id="single_media_input">
                                <label class="form-label">{{ __('Media URL') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                <input type="url" name="media_url" class="form-control" placeholder="https://example.com/image.jpg">
                                <small class="text-muted">{{ __('Direct link to image or video') }}</small>
                            </div>

                            <div class="col-md-12 mb-3" id="carousel_media_input" style="display: none;">
                                <label class="form-label">{{ __('Carousel Images (Max 10)') }}</label>
                                <div id="carousel_urls_container">
                                    <div class="input-group mb-2">
                                        <input type="url" name="media_urls[]" class="form-control" placeholder="https://example.com/slide1.jpg">
                                        <button type="button" class="btn btn-outline-danger remove-url" style="display:none;"><i class="ti ti-trash"></i></button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success" id="add_more_urls">
                                    <i class="ti ti-plus me-1"></i>{{ __('Add Another Image') }}
                                </button>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Schedule Post') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                <input type="datetime-local" name="scheduled_at" class="form-control">
                                <small class="text-muted">{{ __('Leave empty to save as draft') }}</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i>
                            <strong>{{ __('Note') }}:</strong> {{ __('Posts will be saved as draft. You can publish them manually or schedule for later.') }}
                        </div>

                        <div class="text-end">
                            <a href="{{ route('marketing.posts') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Create Post') }}</button>
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
    const mediaType = document.getElementById('media_type');
    const singleInput = document.getElementById('single_media_input');
    const carouselInput = document.getElementById('carousel_media_input');
    const addMoreBtn = document.getElementById('add_more_urls');
    const container = document.getElementById('carousel_urls_container');

    mediaType.addEventListener('change', function() {
        if (this.value === 'carousel') {
            singleInput.style.display = 'none';
            carouselInput.style.display = 'block';
        } else {
            singleInput.style.display = 'block';
            carouselInput.style.display = 'none';
        }
    });

    addMoreBtn.addEventListener('click', function() {
        const rows = container.querySelectorAll('.input-group');
        if (rows.length < 10) {
            const firstRow = container.querySelector('.input-group');
            const newRow = firstRow.cloneNode(true);
            const input = newRow.querySelector('input');
            input.value = '';
            input.placeholder = `https://example.com/slide${rows.length + 1}.jpg`;
            
            const removeBtn = newRow.querySelector('.remove-url');
            removeBtn.style.display = 'block';
            
            container.appendChild(newRow);
            
            removeBtn.addEventListener('click', function() {
                newRow.remove();
            });
        }
    });

    // Handle removal for dynamically added rows
    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-url')) {
            e.target.closest('.input-group').remove();
        }
    });
});
</script>
@endpush
