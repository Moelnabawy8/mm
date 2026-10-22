<?php

namespace Modules\VideoStreaming\Services;

use Modules\VideoStreaming\Entities\Video;
use Modules\VideoStreaming\Entities\VideoQuality;
use Modules\VideoStreaming\Entities\VideoAccessToken;
use Modules\VideoStreaming\Entities\VideoEmbedSettings;
use Modules\VideoStreaming\Entities\VideoAnalytics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class VideoStreamingService
{
    protected GoogleDriveService $googleDrive;
    protected VideoProcessingService $videoProcessing;
    protected HLSEncryptionService $hlsEncryption;
    protected SegmentCacheService $segmentCache;
    protected string $storagePath;

    public function __construct(
        GoogleDriveService $googleDrive,
        VideoProcessingService $videoProcessing,
        HLSEncryptionService $hlsEncryption,
        SegmentCacheService $segmentCache
    ) {
        $this->googleDrive = $googleDrive;
        $this->videoProcessing = $videoProcessing;
        $this->hlsEncryption = $hlsEncryption;
        $this->segmentCache = $segmentCache;
        $this->storagePath = storage_path('app/videos');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Upload and process video.
     */
    public function uploadVideo(UploadedFile $file, int $userId, array $metadata = []): Video
    {
        DB::beginTransaction();

        try {
            // Create video record
            $video = Video::create([
                'title' => $metadata['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $metadata['description'] ?? null,
                'user_id' => $userId,
                'original_filename' => $file->getClientOriginalName(),
                'original_extension' => $file->getClientOriginalExtension(),
                'original_size' => $file->getSize(),
                'status' => 'uploading',
                'watermark_text' => $metadata['watermark_text'] ?? config('videostreaming.player.watermark_text'),
                'watermark_enabled' => $metadata['watermark_enabled'] ?? config('videostreaming.player.watermark_enabled'),
                'domain_lock_enabled' => $metadata['domain_lock_enabled'] ?? true,
                'allowed_domains' => $metadata['allowed_domains'] ?? null,
            ]);

            // Save file temporarily
            $tempPath = $this->storagePath . '/' . $video->uuid;
            if (!is_dir($tempPath)) {
                mkdir($tempPath, 0755, true);
            }

            $localFilePath = $tempPath . '/original.' . $file->getClientOriginalExtension();
            $file->move($tempPath, 'original.' . $file->getClientOriginalExtension());

            // Get video info
            $videoInfo = $this->videoProcessing->getVideoInfo($localFilePath);
            if ($videoInfo) {
                $video->update([
                    'duration' => $videoInfo['duration'],
                    'width' => $videoInfo['width'],
                    'height' => $videoInfo['height'],
                    'metadata' => $videoInfo,
                ]);
            }

            // Generate thumbnail
            $thumbnailPath = $tempPath . '/thumbnail.jpg';
            if ($this->videoProcessing->generateThumbnail($localFilePath, $thumbnailPath)) {
                $video->update(['thumbnail_path' => $thumbnailPath]);
            }

            // Upload to Google Drive
            $driveFileId = $this->googleDrive->uploadLargeFile(
                $localFilePath,
                $video->uuid . '.' . $file->getClientOriginalExtension()
            );

            if (!$driveFileId) {
                throw new Exception('Failed to upload to Google Drive');
            }

            // Make file private
            $this->googleDrive->setPrivate($driveFileId);

            $video->update([
                'google_drive_file_id' => $driveFileId,
                'status' => 'uploaded',
            ]);

            // Create embed settings
            VideoEmbedSettings::create([
                'video_id' => $video->id,
            ]);

            // Clean up local original file (keep thumbnail)
            unlink($localFilePath);

            DB::commit();

            return $video;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Video upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process video for streaming.
     */
    public function processVideo(Video $video): bool
    {
        try {
            $video->update(['status' => 'processing']);

            $tempPath = $this->videoProcessing->getTempPath($video->uuid);
            $localFilePath = $tempPath . '/original.' . $video->original_extension;

            // Download from Google Drive
            if (!$this->googleDrive->downloadFile($video->google_drive_file_id, $localFilePath)) {
                throw new Exception('Failed to download from Google Drive');
            }

            // Prepare encryption
            $encryptionData = $this->hlsEncryption->prepareEncryptionFiles($video, $tempPath);

            // Convert to HLS
            $outputDir = $tempPath . '/hls';
            $results = $this->videoProcessing->convertToHLS(
                $localFilePath,
                $outputDir,
                $encryptionData['key_path'],
                $encryptionData['key_info_path']
            );

            if (empty($results)) {
                throw new Exception('HLS conversion failed');
            }

            // Create folder in Google Drive for HLS files
            $driveFolderId = $this->googleDrive->createFolder($video->uuid . '_hls');

            // Upload HLS files to Google Drive and save quality records
            foreach ($results as $qualityName => $result) {
                if (!$result['success']) {
                    continue;
                }

                // Create quality folder in Drive
                $qualityFolderId = $this->googleDrive->createFolder($qualityName, $driveFolderId);

                // Upload playlist
                $playlistPath = $result['output_dir'] . '/' . $result['playlist'];
                $this->googleDrive->uploadFile(
                    $playlistPath,
                    $result['playlist'],
                    $qualityFolderId,
                    'application/vnd.apple.mpegurl'
                );

                // Upload segments
                $segments = glob($result['output_dir'] . '/*.ts');
                foreach ($segments as $segment) {
                    $this->googleDrive->uploadFile(
                        $segment,
                        basename($segment),
                        $qualityFolderId,
                        'video/mp2t'
                    );
                }

                // Calculate total size
                $totalSize = array_sum(array_map('filesize', $segments));

                // Save quality record
                VideoQuality::create([
                    'video_id' => $video->id,
                    'quality' => $qualityName,
                    'width' => $result['width'],
                    'height' => $result['height'],
                    'bitrate' => $result['bitrate'],
                    'playlist_filename' => $result['playlist'],
                    'google_drive_folder_id' => $qualityFolderId,
                    'segments_count' => $result['segments_count'],
                    'status' => 'ready',
                    'file_size' => $totalSize,
                ]);
            }

            // Upload master playlist
            $masterPlaylistPath = $outputDir . '/master.m3u8';
            if (file_exists($masterPlaylistPath)) {
                $this->googleDrive->uploadFile(
                    $masterPlaylistPath,
                    'master.m3u8',
                    $driveFolderId,
                    'application/vnd.apple.mpegurl'
                );
            }

            $video->update([
                'google_drive_folder_id' => $driveFolderId,
                'status' => 'ready',
                'processed_at' => Carbon::now(),
            ]);

            // Clean up temporary files
            $this->videoProcessing->cleanup($tempPath);

            return true;
        } catch (Exception $e) {
            Log::error('Video processing failed: ' . $e->getMessage());
            $video->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate access token for video streaming.
     */
    public function generateAccessToken(
        Video $video,
        ?int $userId = null,
        ?string $refererDomain = null
    ): VideoAccessToken {
        $expiryMinutes = config('videostreaming.security.signed_url_expiry', 3600) / 60;

        return VideoAccessToken::createForVideo(
            $video,
            $userId,
            request()->ip(),
            request()->userAgent(),
            $refererDomain,
            $expiryMinutes
        );
    }

    /**
     * Validate access token.
     */
    public function validateAccessToken(string $token, Video $video): ?VideoAccessToken
    {
        $accessToken = VideoAccessToken::where('token', $token)
            ->where('video_id', $video->id)
            ->valid()
            ->first();

        if (!$accessToken) {
            return null;
        }

        // Validate domain lock
        if ($video->domain_lock_enabled && config('videostreaming.security.enable_domain_lock')) {
            $referer = request()->header('Referer');
            $refererDomain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

            if ($refererDomain) {
                $allowedDomains = $video->allowed_domains ?? [];
                $configDomains = explode(',', config('videostreaming.security.allowed_domains', ''));
                $allAllowedDomains = array_merge($allowedDomains, $configDomains);

                $isAllowed = false;
                foreach ($allAllowedDomains as $domain) {
                    $domain = trim($domain);
                    if ($domain && (str_ends_with($refererDomain, $domain) || $refererDomain === $domain)) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed && !empty(array_filter($allAllowedDomains))) {
                    Log::warning("Domain lock violation: {$refererDomain} for video {$video->uuid}");
                    return null;
                }
            }
        }

        // Update usage
        $accessToken->incrementRequests();

        return $accessToken;
    }

    /**
     * Get master playlist content with secured URLs.
     */
    public function getMasterPlaylist(Video $video, string $token): ?string
    {
        $qualities = $video->qualities()->where('status', 'ready')->get();

        if ($qualities->isEmpty()) {
            return null;
        }

        $content = "#EXTM3U\n#EXT-X-VERSION:3\n\n";

        $bandwidthMap = [
            '360p' => 800000,
            '480p' => 1400000,
            '720p' => 2800000,
            '1080p' => 5000000,
        ];

        foreach ($qualities as $quality) {
            $bandwidth = $bandwidthMap[$quality->quality] ?? 1000000;
            $playlistUrl = route('video.stream.playlist', [
                'uuid' => $video->uuid,
                'quality' => $quality->quality,
            ]) . '?token=' . $token;

            $content .= sprintf(
                "#EXT-X-STREAM-INF:BANDWIDTH=%d,RESOLUTION=%dx%d,NAME=\"%s\"\n%s\n\n",
                $bandwidth,
                $quality->width,
                $quality->height,
                $quality->label,
                $playlistUrl
            );
        }

        return $content;
    }

    /**
     * Get quality playlist content.
     */
    public function getQualityPlaylist(Video $video, string $quality, string $token): ?string
    {
        $videoQuality = $video->qualities()
            ->where('quality', $quality)
            ->where('status', 'ready')
            ->first();

        if (!$videoQuality) {
            return null;
        }

        $encryptionKey = $video->activeEncryptionKey;
        if (!$encryptionKey) {
            return null;
        }

        // Generate playlist content
        $segmentDuration = config('videostreaming.hls.segment_duration', 10);
        $keyUrl = $this->hlsEncryption->generateSignedKeyUrl($video, $encryptionKey->key_id, $token);

        $content = "#EXTM3U\n";
        $content .= "#EXT-X-VERSION:3\n";
        $content .= "#EXT-X-TARGETDURATION:{$segmentDuration}\n";
        $content .= "#EXT-X-MEDIA-SEQUENCE:0\n";
        $content .= "#EXT-X-PLAYLIST-TYPE:VOD\n";
        $content .= "#EXT-X-KEY:METHOD=AES-128,URI=\"{$keyUrl}\",IV=0x" . $encryptionKey->getIVHex() . "\n";

        // Add segments
        for ($i = 0; $i < $videoQuality->segments_count; $i++) {
            $segmentUrl = route('video.stream.segment', [
                'uuid' => $video->uuid,
                'quality' => $quality,
                'segment' => $i,
            ]) . '?token=' . $token;

            $content .= "#EXTINF:{$segmentDuration}.000,\n";
            $content .= $segmentUrl . "\n";
        }

        $content .= "#EXT-X-ENDLIST\n";

        return $content;
    }

    /**
     * Get video segment (optimized with caching).
     */
    public function getSegment(Video $video, string $quality, int $segmentIndex): ?string
    {
        $cachedContent = $this->segmentCache->get($video->uuid, $quality, $segmentIndex);
        if ($cachedContent !== null) {
            return $cachedContent;
        }

        $videoQuality = $video->qualities()
            ->where('quality', $quality)
            ->where('status', 'ready')
            ->first();

        if (!$videoQuality) {
            return null;
        }

        $segmentFilename = sprintf('segment_%s_%05d.ts', $quality, $segmentIndex);

        $fileId = $this->segmentCache->getFileId($video->uuid, $quality, $segmentIndex);

        if (!$fileId) {
            $fileId = $this->googleDrive->getFileByName(
                $videoQuality->google_drive_folder_id,
                $segmentFilename
            );

            if ($fileId) {
                $this->segmentCache->putFileId($video->uuid, $quality, $segmentIndex, $fileId);
            }
        }

        if (!$fileId) {
            Log::warning("Segment not found: {$video->uuid} {$quality} {$segmentIndex}");
            return null;
        }

        $content = $this->googleDrive->streamFileContent($fileId);

        if ($content) {
            $this->segmentCache->put($video->uuid, $quality, $segmentIndex, $content);

            if (config('videostreaming.cache.preload_enabled', true)) {
                $this->preloadNextSegments($video, $quality, $segmentIndex);
            }
        }

        return $content;
    }

    /**
     * Preload next segments in background.
     */
    protected function preloadNextSegments(Video $video, string $quality, int $currentSegment): void
    {
        $preloadCount = config('videostreaming.cache.preload_count', 3);

        dispatch(function () use ($video, $quality, $currentSegment, $preloadCount) {
            $videoQuality = $video->qualities()
                ->where('quality', $quality)
                ->where('status', 'ready')
                ->first();

            if (!$videoQuality) {
                return;
            }

            for ($i = 1; $i <= $preloadCount; $i++) {
                $nextSegment = $currentSegment + $i;

                if ($nextSegment >= $videoQuality->segments_count) {
                    break;
                }

                if ($this->segmentCache->get($video->uuid, $quality, $nextSegment) !== null) {
                    continue;
                }

                $segmentFilename = sprintf('segment_%s_%05d.ts', $quality, $nextSegment);

                $fileId = $this->segmentCache->getFileId($video->uuid, $quality, $nextSegment);
                if (!$fileId) {
                    $fileId = $this->googleDrive->getFileByName(
                        $videoQuality->google_drive_folder_id,
                        $segmentFilename
                    );
                    if ($fileId) {
                        $this->segmentCache->putFileId($video->uuid, $quality, $nextSegment, $fileId);
                    }
                }

                if ($fileId) {
                    $content = $this->googleDrive->streamFileContent($fileId);
                    if ($content) {
                        $this->segmentCache->put($video->uuid, $quality, $nextSegment, $content);
                        Log::debug("Preloaded segment: {$video->uuid} {$quality} {$nextSegment}");
                    }
                }

                usleep(100000);
            }
        })->afterResponse();
    }

    /**
     * Record analytics event.
     */
    public function recordAnalytics(Video $video, string $sessionId, array $data): VideoAnalytics
    {
        return VideoAnalytics::updateOrCreate(
            [
                'video_id' => $video->id,
                'session_id' => $sessionId,
            ],
            array_merge([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'referer_url' => request()->header('Referer'),
                'referer_domain' => request()->header('Referer')
                    ? parse_url(request()->header('Referer'), PHP_URL_HOST)
                    : null,
            ], $data)
        );
    }

    /**
     * Delete video and all associated data.
     */
    public function deleteVideo(Video $video): bool
    {
        try {
            // Delete from Google Drive
            if ($video->google_drive_file_id) {
                $this->googleDrive->deleteFile($video->google_drive_file_id);
            }

            if ($video->google_drive_folder_id) {
                $this->googleDrive->deleteFile($video->google_drive_folder_id);
            }

            // Delete quality folders
            foreach ($video->qualities as $quality) {
                if ($quality->google_drive_folder_id) {
                    $this->googleDrive->deleteFile($quality->google_drive_folder_id);
                }
            }

            // Soft delete video (cascade will handle relations)
            $video->delete();

            return true;
        } catch (Exception $e) {
            Log::error('Video deletion failed: ' . $e->getMessage());
            return false;
        }
    }
}
