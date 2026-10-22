<?php

return [
    'name' => 'VideoStreaming',

    // Google Drive Settings
    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET', ''),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN', ''),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', ''),
    ],

    // HLS Settings
    'hls' => [
        'segment_duration' => env('HLS_SEGMENT_DURATION', 10), // seconds per segment
        'encryption_enabled' => env('HLS_ENCRYPTION_ENABLED', true),
        'key_rotation_interval' => env('HLS_KEY_ROTATION', 5), // rotate key every N segments
    ],

    // Video Processing Settings
    'processing' => [
        'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
        'output_qualities' => [
            '360p' => ['width' => 640, 'height' => 360, 'bitrate' => '800k'],
            '480p' => ['width' => 854, 'height' => 480, 'bitrate' => '1400k'],
            '720p' => ['width' => 1280, 'height' => 720, 'bitrate' => '2800k'],
            '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
        ],
    ],

    // Security Settings
    'security' => [
        'signed_url_expiry' => env('VIDEO_URL_EXPIRY', 3600), // 1 hour
        'allowed_domains' => env('VIDEO_ALLOWED_DOMAINS', ''),
        'enable_domain_lock' => env('VIDEO_DOMAIN_LOCK', true),
        'enable_ip_restriction' => env('VIDEO_IP_RESTRICTION', false),
        'max_concurrent_views' => env('VIDEO_MAX_VIEWS', 3),
    ],

    // Player Settings
    'player' => [
        'watermark_enabled' => env('VIDEO_WATERMARK', true),
        'watermark_text' => env('VIDEO_WATERMARK_TEXT', ''),
        'disable_right_click' => true,
        'disable_devtools' => true,
        'disable_download' => true,
    ],

    // Cache Settings
    'cache' => [
        'driver' => env('VIDEO_CACHE_DRIVER', 'file'), // 'file' or 'redis'
        'segment_ttl' => env('VIDEO_CACHE_TTL', 86400), // 24 hours
        'max_disk_size_mb' => env('VIDEO_CACHE_MAX_SIZE', 5000), // 5GB
        'preload_enabled' => env('VIDEO_CACHE_PRELOAD', true),
        'preload_count' => env('VIDEO_CACHE_PRELOAD_COUNT', 3), // preload next 3 segments
      ],
];
