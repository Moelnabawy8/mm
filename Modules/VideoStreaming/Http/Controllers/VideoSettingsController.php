<?php

namespace Modules\VideoStreaming\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class VideoSettingsController extends Controller
{
    /**
     * Show settings page.
     */
    public function index()
    {
        $settings = [
            'google_drive' => [
                'client_id' => config('videostreaming.google_drive.client_id'),
                'client_secret' => config('videostreaming.google_drive.client_secret') ? '********' : '',
                'folder_id' => config('videostreaming.google_drive.folder_id'),
                'connected' => !empty(config('videostreaming.google_drive.refresh_token')),
            ],
            'hls' => [
                'segment_duration' => config('videostreaming.hls.segment_duration'),
                'encryption_enabled' => config('videostreaming.hls.encryption_enabled'),
                'key_rotation_interval' => config('videostreaming.hls.key_rotation_interval'),
            ],
            'processing' => [
                'ffmpeg_path' => config('videostreaming.processing.ffmpeg_path'),
                'output_qualities' => config('videostreaming.processing.output_qualities'),
            ],
            'security' => [
                'signed_url_expiry' => config('videostreaming.security.signed_url_expiry'),
                'enable_domain_lock' => config('videostreaming.security.enable_domain_lock'),
                'allowed_domains' => config('videostreaming.security.allowed_domains'),
                'max_concurrent_views' => config('videostreaming.security.max_concurrent_views'),
            ],
            'player' => [
                'watermark_enabled' => config('videostreaming.player.watermark_enabled'),
                'watermark_text' => config('videostreaming.player.watermark_text'),
                'disable_right_click' => config('videostreaming.player.disable_right_click'),
                'disable_devtools' => config('videostreaming.player.disable_devtools'),
            ],
        ];

        return view('videostreaming::settings.index', compact('settings'));
    }

    /**
     * Update settings.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'google_drive.client_id' => 'nullable|string',
            'google_drive.client_secret' => 'nullable|string',
            'google_drive.folder_id' => 'nullable|string',
            'hls.segment_duration' => 'nullable|integer|min:2|max:30',
            'hls.encryption_enabled' => 'nullable|boolean',
            'security.signed_url_expiry' => 'nullable|integer|min:300|max:86400',
            'security.enable_domain_lock' => 'nullable|boolean',
            'security.allowed_domains' => 'nullable|string',
            'player.watermark_enabled' => 'nullable|boolean',
            'player.watermark_text' => 'nullable|string|max:100',
        ]);

        // Update .env file
        $envUpdates = [];

        if ($request->filled('google_drive.client_id')) {
            $envUpdates['GOOGLE_DRIVE_CLIENT_ID'] = $request->input('google_drive.client_id');
        }
        if ($request->filled('google_drive.client_secret') && $request->input('google_drive.client_secret') !== '********') {
            $envUpdates['GOOGLE_DRIVE_CLIENT_SECRET'] = $request->input('google_drive.client_secret');
        }
        if ($request->filled('google_drive.folder_id')) {
            $envUpdates['GOOGLE_DRIVE_FOLDER_ID'] = $request->input('google_drive.folder_id');
        }
        if ($request->filled('hls.segment_duration')) {
            $envUpdates['HLS_SEGMENT_DURATION'] = $request->input('hls.segment_duration');
        }
        if ($request->has('hls.encryption_enabled')) {
            $envUpdates['HLS_ENCRYPTION_ENABLED'] = $request->boolean('hls.encryption_enabled') ? 'true' : 'false';
        }
        if ($request->filled('security.signed_url_expiry')) {
            $envUpdates['VIDEO_URL_EXPIRY'] = $request->input('security.signed_url_expiry');
        }
        if ($request->has('security.enable_domain_lock')) {
            $envUpdates['VIDEO_DOMAIN_LOCK'] = $request->boolean('security.enable_domain_lock') ? 'true' : 'false';
        }
        if ($request->filled('security.allowed_domains')) {
            $envUpdates['VIDEO_ALLOWED_DOMAINS'] = $request->input('security.allowed_domains');
        }
        if ($request->has('player.watermark_enabled')) {
            $envUpdates['VIDEO_WATERMARK'] = $request->boolean('player.watermark_enabled') ? 'true' : 'false';
        }
        if ($request->filled('player.watermark_text')) {
            $envUpdates['VIDEO_WATERMARK_TEXT'] = $request->input('player.watermark_text');
        }

        if (!empty($envUpdates)) {
            $this->updateEnvFile($envUpdates);
            Artisan::call('config:clear');
        }

        return response()->json([
            'success' => true,
            'message' => __('Settings updated successfully.'),
        ]);
    }

    /**
     * Update .env file.
     */
    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Test Google Drive connection.
     */
    public function testGoogleDrive(): JsonResponse
    {
        try {
            $service = app(\Modules\VideoStreaming\Services\GoogleDriveService::class);
            $files = $service->listFiles();

            return response()->json([
                'success' => true,
                'message' => __('Google Drive connected successfully.'),
                'files_count' => count($files),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('Google Drive connection failed: ') . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test FFmpeg installation.
     */
    public function testFfmpeg(): JsonResponse
    {
        $ffmpegPath = config('videostreaming.processing.ffmpeg_path', 'ffmpeg');
        $output = shell_exec("{$ffmpegPath} -version 2>&1");

        if ($output && str_contains($output, 'ffmpeg version')) {
            preg_match('/ffmpeg version ([^\s]+)/', $output, $matches);
            $version = $matches[1] ?? 'unknown';

            return response()->json([
                'success' => true,
                'message' => __('FFmpeg is installed.'),
                'version' => $version,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('FFmpeg is not installed or not found at the specified path.'),
        ], 500);
    }
}
