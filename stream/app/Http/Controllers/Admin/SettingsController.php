<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'storage_driver' => config('securestream.storage_driver'),
            'stream_token_expiry' => config('securestream.stream_token_expiry'),
            'hls_key_expiry' => config('securestream.hls_key_expiry'),
            'ffmpeg_path' => config('securestream.ffmpeg_path'),
            'watermark_enabled' => config('securestream.watermark.enabled'),
            'google_drive_configured' => !empty(config('securestream.google_drive.client_id')),
        ];

        return view('admin.settings.index', compact('settings'));
    }
}
