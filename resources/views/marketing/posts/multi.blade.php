@extends('layouts.admin')

@section('page-title')
    {{ __('Multi-Post') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.posts') }}">{{ __('Posts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Multi-Post') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="ti ti-send me-2"></i>{{ __('Post to Multiple Platforms') }}</h5>
                    <small class="text-muted">{{ __('Select platforms and publish your content everywhere at once') }}</small>
                </div>
                <div class="card-body">
                    @if($accounts->isEmpty())
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-1"></i>
                            {{ __('No active accounts found.') }}
                            <a href="{{ route('marketing.accounts.create') }}">{{ __('Connect an account first') }}</a>.
                        </div>
                    @else
                        <form action="{{ route('marketing.multipost.store') }}" method="POST" id="multiPostForm">
                            @csrf

                            {{-- Platform Multi-Select --}}
                            <div class="mb-4">
                                <label class="form-label fw-bold">{{ __('Select Platforms') }} <span class="text-danger">*</span></label>
                                <small class="text-muted d-block mb-2">{{ __('Click to select/deselect platforms') }}</small>

                                <div class="row g-2" id="platformGrid">
                                    @foreach($accounts as $account)
                                        <div class="col-md-4 col-sm-6">
                                            <div class="platform-card border rounded-3 p-3 text-center cursor-pointer position-relative {{ in_array($account->id, old('account_ids', [])) ? 'selected' : '' }}"
                                                 data-account-id="{{ $account->id }}"
                                                 data-platform="{{ $account->platform }}"
                                                 style="transition: all 0.2s ease; cursor: pointer;">

                                                <input type="checkbox"
                                                       name="account_ids[]"
                                                       value="{{ $account->id }}"
                                                       class="d-none platform-checkbox"
                                                       {{ in_array($account->id, old('account_ids', [])) ? 'checked' : '' }}>

                                                {{-- Checkmark badge --}}
                                                <span class="position-absolute top-0 end-0 m-2 badge bg-success rounded-circle check-badge" style="display: none; width: 22px; height: 22px; line-height: 16px;">
                                                    <i class="ti ti-check" style="font-size: 12px;"></i>
                                                </span>

                                                <i class="{{ $account->platform_icon }}" style="font-size: 2rem; color: {{ $account->platform_color }};"></i>
                                                <div class="mt-2 fw-semibold text-truncate" title="{{ $account->account_name }}">
                                                    {{ Str::limit($account->account_name, 20) }}
                                                </div>
                                                <small class="text-muted text-capitalize">{{ $account->platform }}</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">{{ __('Select All') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">{{ __('Deselect All') }}</button>
                                    <span class="ms-2 text-muted" id="selectedCount">0 {{ __('selected') }}</span>
                                </div>

                                @error('account_ids')
                                    <div class="text-danger mt-1"><small>{{ $message }}</small></div>
                                @enderror
                            </div>

                            {{-- Post Content --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold">{{ __('Content') }} <span class="text-danger">*</span></label>
                                <textarea name="content" class="form-control" rows="6"
                                          placeholder="{{ __('Write your post content here...') }}"
                                          required maxlength="5000" id="contentInput">{{ old('content') }}</textarea>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted">{{ __('Tip: Keep it engaging and include relevant hashtags') }}</small>
                                    <small class="text-muted"><span id="charCount">0</span>/5000</small>
                                </div>
                            </div>

                            {{-- Media --}}
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">{{ __('Media Type') }}</label>
                                    <select name="media_type" class="form-control" id="mediaType">
                                        <option value="image">{{ __('Photo / Image') }}</option>
                                        <option value="video">{{ __('Video') }}</option>
                                    </select>
                                </div>

                                <div class="col-md-8 mb-3">
                                    <label class="form-label">{{ __('Media URL') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                    <input type="url" name="media_url" class="form-control" value="{{ old('media_url') }}"
                                           placeholder="https://example.com/image.jpg">
                                    <small class="text-muted">{{ __('Direct link to image or video. Required for Instagram.') }}</small>
                                </div>
                            </div>

                            {{-- Schedule --}}
                            <div class="mb-3">
                                <label class="form-label">{{ __('Schedule Post') }} <small class="text-muted">({{ __('Optional') }})</small></label>
                                <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}">
                                <small class="text-muted">{{ __('Leave empty to publish immediately') }}</small>
                            </div>

                            {{-- Platform Notices --}}
                            <div class="alert alert-info" id="platformNotices" style="display: none;">
                                <i class="ti ti-info-circle me-1"></i>
                                <span id="noticeText"></span>
                            </div>

                            <div class="text-end">
                                <a href="{{ route('marketing.posts') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="ti ti-send me-1"></i>{{ __('Publish to Selected Platforms') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<style>
    .platform-card {
        background: var(--bs-card-bg, #fff);
        border: 2px solid var(--bs-border-color, #dee2e6) !important;
    }
    .platform-card:hover {
        border-color: var(--bs-primary, #6366f1) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transform: translateY(-1px);
    }
    .platform-card.selected {
        border-color: var(--bs-success, #22c55e) !important;
        background: rgba(34, 197, 94, 0.05);
        box-shadow: 0 0 0 1px rgba(34, 197, 94, 0.2);
    }
    .platform-card.selected .check-badge {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.platform-card');
    const selectedCountEl = document.getElementById('selectedCount');
    const contentInput = document.getElementById('contentInput');
    const charCountEl = document.getElementById('charCount');
    const noticesEl = document.getElementById('platformNotices');
    const noticeTextEl = document.getElementById('noticeText');

    function updateCount() {
        const checked = document.querySelectorAll('.platform-checkbox:checked').length;
        selectedCountEl.textContent = checked + ' {{ __("selected") }}';
        updateNotices();
    }

    function updateNotices() {
        const selected = document.querySelectorAll('.platform-checkbox:checked');
        const platforms = [...selected].map(cb => cb.closest('.platform-card').dataset.platform);
        const notices = [];

        if (platforms.includes('instagram')) {
            notices.push('{{ __("Instagram requires a media URL (image or video).") }}');
        }
        if (platforms.includes('telegram')) {
            notices.push('{{ __("Telegram posts use the bot_token & channel_id from account metadata.") }}');
        }
        if (platforms.includes('twitter') || platforms.includes('x')) {
            notices.push('{{ __("X/Twitter posts are limited to 280 characters for text.") }}');
        }

        if (notices.length > 0) {
            noticeTextEl.innerHTML = notices.join('<br>');
            noticesEl.style.display = 'block';
        } else {
            noticesEl.style.display = 'none';
        }
    }

    cards.forEach(card => {
        card.addEventListener('click', function() {
            const checkbox = this.querySelector('.platform-checkbox');
            checkbox.checked = !checkbox.checked;
            this.classList.toggle('selected', checkbox.checked);
            updateCount();
        });
    });

    document.getElementById('selectAll').addEventListener('click', function() {
        cards.forEach(card => {
            card.querySelector('.platform-checkbox').checked = true;
            card.classList.add('selected');
        });
        updateCount();
    });

    document.getElementById('deselectAll').addEventListener('click', function() {
        cards.forEach(card => {
            card.querySelector('.platform-checkbox').checked = false;
            card.classList.remove('selected');
        });
        updateCount();
    });

    contentInput.addEventListener('input', function() {
        charCountEl.textContent = this.value.length;
    });
    charCountEl.textContent = contentInput.value.length;

    updateCount();
});
</script>
@endpush
