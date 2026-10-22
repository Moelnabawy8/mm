<?php

namespace Modules\VideoStreaming\Services;

use Modules\VideoStreaming\Entities\Video;
use Modules\VideoStreaming\Entities\VideoQuality;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class VideoProcessingService
{
    protected string $ffmpegPath;
    protected string $ffprobePath;
    protected array $qualities;
    protected string $tempPath;

    public function __construct()
    {
        $this->ffmpegPath = config('videostreaming.processing.ffmpeg_path', 'ffmpeg');
        $this->ffprobePath = config('videostreaming.processing.ffprobe_path', 'ffprobe');
        $this->qualities = config('videostreaming.processing.output_qualities', []);
        $this->tempPath = storage_path('app/video-processing');

        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Get video information using FFprobe.
     */
    public function getVideoInfo(string $filePath): ?array
    {
        try {
            $command = sprintf(
                '%s -v quiet -print_format json -show_format -show_streams "%s"',
                $this->ffprobePath,
                $filePath
            );

            $output = shell_exec($command);
            $info = json_decode($output, true);

            if (!$info) {
                return null;
            }

            $videoStream = null;
            $audioStream = null;

            foreach ($info['streams'] ?? [] as $stream) {
                if ($stream['codec_type'] === 'video' && !$videoStream) {
                    $videoStream = $stream;
                } elseif ($stream['codec_type'] === 'audio' && !$audioStream) {
                    $audioStream = $stream;
                }
            }

            return [
                'duration' => (int) ($info['format']['duration'] ?? 0),
                'size' => (int) ($info['format']['size'] ?? 0),
                'bitrate' => (int) ($info['format']['bit_rate'] ?? 0),
                'width' => (int) ($videoStream['width'] ?? 0),
                'height' => (int) ($videoStream['height'] ?? 0),
                'codec' => $videoStream['codec_name'] ?? null,
                'fps' => $this->parseFps($videoStream['r_frame_rate'] ?? '0/1'),
                'audio_codec' => $audioStream['codec_name'] ?? null,
                'audio_bitrate' => (int) ($audioStream['bit_rate'] ?? 0),
            ];
        } catch (Exception $e) {
            Log::error('FFprobe failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse FPS from ratio string.
     */
    protected function parseFps(string $fpsRatio): float
    {
        $parts = explode('/', $fpsRatio);
        if (count($parts) === 2 && (int) $parts[1] > 0) {
            return (float) $parts[0] / (float) $parts[1];
        }
        return (float) $parts[0];
    }

    /**
     * Generate thumbnail from video.
     */
    public function generateThumbnail(string $videoPath, string $outputPath, int $atSecond = 5): bool
    {
        try {
            $command = sprintf(
                '%s -y -i "%s" -ss %d -vframes 1 -q:v 2 "%s"',
                $this->ffmpegPath,
                $videoPath,
                $atSecond,
                $outputPath
            );

            exec($command, $output, $returnCode);

            return $returnCode === 0 && file_exists($outputPath);
        } catch (Exception $e) {
            Log::error('Thumbnail generation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert video to HLS format with multiple qualities.
     */
    public function convertToHLS(
        string $inputPath,
        string $outputDir,
        string $encryptionKeyPath,
        string $keyInfoPath,
        array $selectedQualities = null
    ): array {
        $results = [];
        $qualitiesToProcess = $selectedQualities ?? array_keys($this->qualities);

        // Get input video info
        $videoInfo = $this->getVideoInfo($inputPath);
        $inputHeight = $videoInfo['height'] ?? 1080;

        // Create output directory
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($qualitiesToProcess as $qualityName) {
            if (!isset($this->qualities[$qualityName])) {
                continue;
            }

            $quality = $this->qualities[$qualityName];

            // Skip if input video is smaller than target quality
            if ($inputHeight < $quality['height']) {
                continue;
            }

            $qualityDir = $outputDir . '/' . $qualityName;
            if (!is_dir($qualityDir)) {
                mkdir($qualityDir, 0755, true);
            }

            $result = $this->processQuality(
                $inputPath,
                $qualityDir,
                $qualityName,
                $quality,
                $encryptionKeyPath,
                $keyInfoPath
            );

            if ($result['success']) {
                $results[$qualityName] = $result;
            }
        }

        // Generate master playlist
        $this->generateMasterPlaylist($outputDir, $results);

        return $results;
    }

    /**
     * Process single quality.
     */
    protected function processQuality(
        string $inputPath,
        string $outputDir,
        string $qualityName,
        array $quality,
        string $encryptionKeyPath,
        string $keyInfoPath
    ): array {
        try {
            $playlistName = "index_{$qualityName}.m3u8";
            $segmentPattern = "segment_{$qualityName}_%05d.ts";

            $segmentDuration = config('videostreaming.hls.segment_duration', 10);

            // FFmpeg command for HLS with encryption
            $command = sprintf(
                '%s -y -i "%s" ' .
                '-vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" ' .
                '-c:v libx264 -preset fast -crf 23 ' .
                '-c:a aac -b:a 128k ' .
                '-b:v %s -maxrate %s -bufsize %s ' .
                '-hls_time %d ' .
                '-hls_list_size 0 ' .
                '-hls_segment_filename "%s/%s" ' .
                '-hls_key_info_file "%s" ' .
                '-hls_playlist_type vod ' .
                '-f hls "%s/%s"',
                $this->ffmpegPath,
                $inputPath,
                $quality['width'],
                $quality['height'],
                $quality['width'],
                $quality['height'],
                $quality['bitrate'],
                $quality['bitrate'],
                $this->calculateBufferSize($quality['bitrate']),
                $segmentDuration,
                $outputDir,
                $segmentPattern,
                $keyInfoPath,
                $outputDir,
                $playlistName
            );

            exec($command . ' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error("FFmpeg failed for {$qualityName}: " . implode("\n", $output));
                return ['success' => false, 'error' => implode("\n", $output)];
            }

            // Count segments
            $segments = glob($outputDir . "/*.ts");

            return [
                'success' => true,
                'quality' => $qualityName,
                'width' => $quality['width'],
                'height' => $quality['height'],
                'bitrate' => $quality['bitrate'],
                'playlist' => $playlistName,
                'segments_count' => count($segments),
                'output_dir' => $outputDir,
            ];
        } catch (Exception $e) {
            Log::error("Quality processing failed for {$qualityName}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calculate buffer size from bitrate.
     */
    protected function calculateBufferSize(string $bitrate): string
    {
        $value = (int) $bitrate;
        return ($value * 2) . 'k';
    }

    /**
     * Generate master playlist.
     */
    protected function generateMasterPlaylist(string $outputDir, array $qualities): void
    {
        $content = "#EXTM3U\n#EXT-X-VERSION:3\n\n";

        $bandwidthMap = [
            '360p' => 800000,
            '480p' => 1400000,
            '720p' => 2800000,
            '1080p' => 5000000,
        ];

        foreach ($qualities as $qualityName => $quality) {
            if (!$quality['success']) {
                continue;
            }

            $bandwidth = $bandwidthMap[$qualityName] ?? 1000000;
            $content .= sprintf(
                "#EXT-X-STREAM-INF:BANDWIDTH=%d,RESOLUTION=%dx%d,NAME=\"%s\"\n%s/%s\n\n",
                $bandwidth,
                $quality['width'],
                $quality['height'],
                $qualityName,
                $qualityName,
                $quality['playlist']
            );
        }

        file_put_contents($outputDir . '/master.m3u8', $content);
    }

    /**
     * Clean up temporary files.
     */
    public function cleanup(string $directory): bool
    {
        try {
            if (is_dir($directory)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }

                return rmdir($directory);
            }
            return true;
        } catch (Exception $e) {
            Log::error('Cleanup failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get temporary processing path for a video.
     */
    public function getTempPath(string $videoUuid): string
    {
        $path = $this->tempPath . '/' . $videoUuid;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }
}
