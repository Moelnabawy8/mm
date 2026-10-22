@extends('layouts.admin')

@section('page-title')
    {{ __('Portal Settings') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.index') }}">{{ __('Public Tickets') }}</a></li>
    <li class="breadcrumb-item">{{ __('Settings') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <form action="{{ route('ticket-management.settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-link me-2"></i>{{ __('Portal URL') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Public Portal URL') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('/ticket-portal') }}/</span>
                                <input type="text" class="form-control" value="{{ $settings->portal_slug }}" readonly>
                                <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $settings->portal_url }}')">
                                    <i class="ti ti-copy"></i>
                                </button>
                            </div>
                            <small class="text-muted">{{ __('Share this URL with your customers to submit tickets') }}</small>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-warning" id="regenerateSlugBtn">
                                <i class="ti ti-refresh"></i> {{ __('Regenerate URL') }}
                            </button>
                            <a href="{{ $settings->portal_url }}" target="_blank" class="btn btn-info">
                                <i class="ti ti-external-link"></i> {{ __('Preview') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-settings me-2"></i>{{ __('General Settings') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="portal_enabled" name="portal_enabled" 
                                        {{ $settings->portal_enabled ? 'checked' : '' }}>
                                    <label class="form-check-label" for="portal_enabled">{{ __('Enable Public Portal') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="portal_title" class="form-label">{{ __('Portal Title (English)') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('portal_title') is-invalid @enderror" 
                                id="portal_title" name="portal_title" value="{{ old('portal_title', $settings->portal_title) }}" required>
                            @error('portal_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="portal_title_ar" class="form-label">{{ __('Portal Title (Arabic)') }}</label>
                            <input type="text" class="form-control" id="portal_title_ar" name="portal_title_ar" 
                                value="{{ old('portal_title_ar', $settings->portal_title_ar) }}" dir="rtl">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="portal_description" class="form-label">{{ __('Portal Description (English)') }}</label>
                            <textarea class="form-control" id="portal_description" name="portal_description" rows="3">{{ old('portal_description', $settings->portal_description) }}</textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="portal_description_ar" class="form-label">{{ __('Portal Description (Arabic)') }}</label>
                            <textarea class="form-control" id="portal_description_ar" name="portal_description_ar" rows="3" dir="rtl">{{ old('portal_description_ar', $settings->portal_description_ar) }}</textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="portal_logo" class="form-label">{{ __('Portal Logo') }}</label>
                            <input type="file" class="form-control" id="portal_logo" name="portal_logo" accept="image/*">
                            @if($settings->portal_logo)
                                <small class="text-muted">{{ __('Current') }}: <a href="{{ asset('storage/' . $settings->portal_logo) }}" target="_blank">{{ __('View') }}</a></small>
                            @endif
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="portal_primary_color" class="form-label">{{ __('Primary Color') }}</label>
                            <input type="color" class="form-control form-control-color w-100" id="portal_primary_color" 
                                name="portal_primary_color" value="{{ old('portal_primary_color', $settings->portal_primary_color ?? '#6366f1') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="portal_secondary_color" class="form-label">{{ __('Secondary Color') }}</label>
                            <input type="color" class="form-control form-control-color w-100" id="portal_secondary_color" 
                                name="portal_secondary_color" value="{{ old('portal_secondary_color', $settings->portal_secondary_color ?? '#8b5cf6') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-forms me-2"></i>{{ __('Form Requirements') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_email" name="require_email" 
                                    {{ $settings->require_email ? 'checked' : '' }}>
                                <label class="form-check-label" for="require_email">{{ __('Require Email') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_phone" name="require_phone" 
                                    {{ $settings->require_phone ? 'checked' : '' }}>
                                <label class="form-check-label" for="require_phone">{{ __('Require Phone') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-paperclip me-2"></i>{{ __('Attachment Settings') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="allow_attachments" name="allow_attachments" 
                                    {{ $settings->allow_attachments ? 'checked' : '' }}>
                                <label class="form-check-label" for="allow_attachments">{{ __('Allow Attachments') }}</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="max_attachments" class="form-label">{{ __('Max Attachments per Ticket') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('max_attachments') is-invalid @enderror" 
                                id="max_attachments" name="max_attachments" 
                                value="{{ old('max_attachments', $settings->max_attachments ?? 5) }}" min="1" max="10" required>
                            @error('max_attachments')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="max_file_size" class="form-label">{{ __('Max File Size (MB)') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('max_file_size') is-invalid @enderror" 
                                id="max_file_size" name="max_file_size" 
                                value="{{ old('max_file_size', $settings->max_file_size ?? 10) }}" min="1" max="50" required>
                            @error('max_file_size')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="allowed_file_types" class="form-label">{{ __('Allowed File Types') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('allowed_file_types') is-invalid @enderror" 
                                id="allowed_file_types" name="allowed_file_types" 
                                value="{{ old('allowed_file_types', $settings->allowed_file_types ?? 'jpg,jpeg,png,pdf,doc,docx') }}" required>
                            <small class="text-muted">{{ __('Comma separated, e.g., jpg,png,pdf') }}</small>
                            @error('allowed_file_types')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-bell me-2"></i>{{ __('Notifications') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                    {{ $settings->email_notifications ? 'checked' : '' }}>
                                <label class="form-check-label" for="email_notifications">{{ __('Email Notifications') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="whatsapp_notifications" name="whatsapp_notifications" 
                                    {{ $settings->whatsapp_notifications ? 'checked' : '' }}>
                                <label class="form-check-label" for="whatsapp_notifications">{{ __('WhatsApp Notifications') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-check me-2"></i>{{ __('Success Message') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="success_message" class="form-label">{{ __('Success Message (English)') }}</label>
                            <textarea class="form-control" id="success_message" name="success_message" rows="3">{{ old('success_message', $settings->success_message) }}</textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="success_message_ar" class="form-label">{{ __('Success Message (Arabic)') }}</label>
                            <textarea class="form-control" id="success_message_ar" name="success_message_ar" rows="3" dir="rtl">{{ old('success_message_ar', $settings->success_message_ar) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-2"></i>{{ __('Save Settings') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    
    document.getElementById('regenerateSlugBtn').addEventListener('click', async function() {
        if (!confirm('{{ __("Are you sure you want to regenerate the portal URL? All existing links will stop working.") }}')) {
            return;
        }
        
        try {
            const response = await fetch('{{ route("ticket-management.regenerate-slug") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                toastrs('Success', '{{ __("Portal URL regenerated successfully") }}', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                toastrs('Error', data.error || 'Failed to regenerate URL', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            toastrs('Error', 'Something went wrong', 'error');
        }
    });
});

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        toastrs('Success', '{{ __("URL copied to clipboard") }}', 'success');
    });
}
</script>
@endsection
