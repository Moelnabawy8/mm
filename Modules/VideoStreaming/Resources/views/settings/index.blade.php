@extends('layouts.admin')
@section('page-title')
    {{ __('Video Streaming Settings') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('videostreaming.index') }}">{{ __('Video Streaming') }}</a></li>
    <li class="breadcrumb-item">{{ __('Settings') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <!-- Google Drive Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-brand-google-drive me-2"></i>{{ __('Google Drive Configuration') }}
                </h5>
            </div>
            <div class="card-body">
                <form id="googleDriveForm">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Client ID') }}</label>
                        <input type="text" name="google_drive_client_id" class="form-control" 
                               value="{{ $settings['google_drive']['client_id'] ?? '' }}" 
                               placeholder="{{ __('Enter Google OAuth Client ID') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Client Secret') }}</label>
                        <input type="password" name="google_drive_client_secret" class="form-control" 
                               placeholder="{{ __('Enter Google OAuth Client Secret') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Folder ID') }}</label>
                        <input type="text" name="google_drive_folder_id" class="form-control" 
                               value="{{ $settings['google_drive']['folder_id'] ?? '' }}"
                               placeholder="{{ __('Google Drive folder ID for video storage') }}">
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            @if($settings['google_drive']['connected'] ?? false)
                                <span class="badge bg-success">
                                    <i class="ti ti-check me-1"></i>{{ __('Connected') }}
                                </span>
                            @else
                                <span class="badge bg-warning">
                                    <i class="ti ti-alert-circle me-1"></i>{{ __('Not Connected') }}
                                </span>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>{{ __('Save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- HLS Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-video me-2"></i>{{ __('HLS Streaming Settings') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Segment Duration (seconds)') }}</label>
                            <input type="number" name="segment_duration" class="form-control" 
                                   value="{{ $settings['hls']['segment_duration'] ?? 10 }}" min="2" max="30">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Key Rotation Interval') }}</label>
                            <input type="number" name="key_rotation_interval" class="form-control" 
                                   value="{{ $settings['hls']['key_rotation_interval'] ?? 5 }}" min="1" max="50">
                        </div>
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="encryption_enabled" 
                           id="hlsEncryption" {{ ($settings['hls']['encryption_enabled'] ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="hlsEncryption">
                        {{ __('Enable AES-128 Encryption') }}
                    </label>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-shield me-2"></i>{{ __('Security Settings') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('Signed URL Expiry (seconds)') }}</label>
                    <input type="number" name="signed_url_expiry" class="form-control" 
                           value="{{ $settings['security']['signed_url_expiry'] ?? 3600 }}" min="300" max="86400">
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="enable_domain_lock" 
                               id="domainLock" {{ ($settings['security']['enable_domain_lock'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="domainLock">
                            {{ __('Enable Domain Lock') }}
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Allowed Domains') }}</label>
                    <textarea name="allowed_domains" class="form-control" rows="3" 
                              placeholder="example.com,subdomain.example.com">{{ $settings['security']['allowed_domains'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        <!-- Player Settings -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="ti ti-player-play me-2"></i>{{ __('Player Settings') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="watermark_enabled" 
                               id="watermarkEnabled" {{ ($settings['player']['watermark_enabled'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="watermarkEnabled">
                            {{ __('Enable Watermark') }}
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Default Watermark Text') }}</label>
                    <input type="text" name="watermark_text" class="form-control" 
                           value="{{ $settings['player']['watermark_text'] ?? '' }}"
                           placeholder="{{ __('Your brand name') }}">
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="disable_right_click" 
                               id="disableRightClick" {{ ($settings['player']['disable_right_click'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="disableRightClick">
                            {{ __('Disable Right Click') }}
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('System Status') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>{{ __('Google Drive') }}</span>
                    @if($settings['google_drive']['connected'] ?? false)
                        <span class="badge bg-success">{{ __('Connected') }}</span>
                    @else
                        <span class="badge bg-warning">{{ __('Not Connected') }}</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>{{ __('HLS Encryption') }}</span>
                    <span class="badge bg-success">{{ __('Enabled') }}</span>
                </div>
            </div>
        </div>

        <!-- Output Qualities -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Output Qualities') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><strong>360P</strong> <span class="text-muted small">(640x360)</span></span>
                    <span class="badge bg-secondary">800k</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><strong>480P</strong> <span class="text-muted small">(854x480)</span></span>
                    <span class="badge bg-secondary">1400k</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><strong>720P</strong> <span class="text-muted small">(1280x720)</span></span>
                    <span class="badge bg-secondary">2800k</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span><strong>1080P</strong> <span class="text-muted small">(1920x1080)</span></span>
                    <span class="badge bg-secondary">5000k</span>
                </div>
            </div>
        </div>

        <!-- Help -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Setup Guide') }}</h5>
            </div>
            <div class="card-body">
                <ol class="small mb-0">
                    <li class="mb-2">{{ __('Create a Google Cloud project') }}</li>
                    <li class="mb-2">{{ __('Enable Drive API') }}</li>
                    <li class="mb-2">{{ __('Create OAuth credentials') }}</li>
                    <li class="mb-2">{{ __('Enter Client ID and Secret') }}</li>
                    <li class="mb-2">{{ __('Install FFmpeg on server') }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
