@extends('layouts.admin')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="max-w-3xl space-y-6">
    <!-- Storage Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-database text-indigo-500"></i> Storage Configuration
        </h3>
        <div class="space-y-4">
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">Active Storage Driver</p>
                    <p class="text-xs text-gray-500">Set via STORAGE_DRIVER in .env</p>
                </div>
                <span class="px-3 py-1.5 text-sm rounded-lg font-medium {{ $settings['storage_driver'] === 'google' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">
                    <i class="fas {{ $settings['storage_driver'] === 'google' ? 'fa-cloud' : 'fa-hdd' }} mr-1"></i>
                    {{ ucfirst($settings['storage_driver']) }}
                </span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">Google Drive API</p>
                    <p class="text-xs text-gray-500">OAuth credentials status</p>
                </div>
                @if($settings['google_drive_configured'])
                    <span class="px-3 py-1.5 text-sm rounded-lg font-medium bg-green-100 text-green-700">
                        <i class="fas fa-check-circle mr-1"></i> Configured
                    </span>
                @else
                    <span class="px-3 py-1.5 text-sm rounded-lg font-medium bg-amber-100 text-amber-700">
                        <i class="fas fa-exclamation-circle mr-1"></i> Not Configured
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Streaming Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-play-circle text-indigo-500"></i> Streaming Configuration
        </h3>
        <div class="space-y-4">
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">Stream Token Expiry</p>
                    <p class="text-xs text-gray-500">STREAM_TOKEN_EXPIRY in .env</p>
                </div>
                <span class="text-sm font-medium text-gray-800">{{ $settings['stream_token_expiry'] }} minutes</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">HLS Key Expiry</p>
                    <p class="text-xs text-gray-500">HLS_KEY_EXPIRY in .env</p>
                </div>
                <span class="text-sm font-medium text-gray-800">{{ $settings['hls_key_expiry'] }} minutes</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">FFmpeg Path</p>
                    <p class="text-xs text-gray-500">Required for video processing</p>
                </div>
                <span class="text-sm font-mono text-gray-600">{{ $settings['ffmpeg_path'] }}</span>
            </div>
        </div>
    </div>

    <!-- Security Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-shield-alt text-indigo-500"></i> Security Configuration
        </h3>
        <div class="space-y-4">
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">Dynamic Watermark</p>
                    <p class="text-xs text-gray-500">Overlay user email on video player</p>
                </div>
                <span class="px-3 py-1.5 text-sm rounded-lg font-medium {{ $settings['watermark_enabled'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                    {{ $settings['watermark_enabled'] ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-gray-100">
                <div>
                    <p class="text-sm font-medium text-gray-700">HLS AES-128 Encryption</p>
                    <p class="text-xs text-gray-500">Segment-level encryption</p>
                </div>
                <span class="px-3 py-1.5 text-sm rounded-lg font-medium bg-green-100 text-green-700">
                    <i class="fas fa-lock mr-1"></i> Active
                </span>
            </div>
            <div class="flex items-center justify-between py-3">
                <div>
                    <p class="text-sm font-medium text-gray-700">Domain Restriction</p>
                    <p class="text-xs text-gray-500">Embed whitelist enforcement</p>
                </div>
                <span class="px-3 py-1.5 text-sm rounded-lg font-medium bg-green-100 text-green-700">
                    <i class="fas fa-lock mr-1"></i> Active
                </span>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
        <i class="fas fa-info-circle mr-2"></i>
        Most settings are configured via the <code class="bg-blue-100 px-1 py-0.5 rounded">.env</code> file. After changing .env values, clear the config cache with <code class="bg-blue-100 px-1 py-0.5 rounded">php artisan config:clear</code>.
    </div>
</div>
@endsection
