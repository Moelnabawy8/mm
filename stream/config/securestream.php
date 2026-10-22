<?php

return [

    'storage_driver' => env('STORAGE_DRIVER', 'local'),

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    ],

    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),

    'stream_token_expiry' => env('STREAM_TOKEN_EXPIRY', 10),

    'hls_key_expiry' => env('HLS_KEY_EXPIRY', 5),

    'allowed_resolutions' => ['1080p', '720p', '480p', '360p'],

    'chunk_size' => 5 * 1024 * 1024, // 5MB chunks

    'watermark' => [
        'enabled' => true,
        'opacity' => 0.3,
        'reposition_interval' => 30, // seconds
    ],

];
