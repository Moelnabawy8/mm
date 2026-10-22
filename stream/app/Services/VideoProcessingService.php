<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class VideoProcessingService
{
    protected string $ffmpegPath;

    public function __construct()
    {
        $this->ffmpegPath = config('securestream.ffmpeg_path', '/usr/bin/ffmpeg');
    }

    public function convertToHls(string $inputPath, string $outputDir, string $encryptionKeyPath, string $keyInfoPath): bool
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $playlistPath = $outputDir . '/playlist.m3u8';
        $segmentPath = $outputDir . '/segment_%03d.ts';

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-profile:v', 'baseline',
            '-level', '3.0',
            '-start_number', '0',
            '-hls_time', '10',
            '-hls_list_size', '0',
            '-hls_segment_filename', $segmentPath,
            '-hls_key_info_file', $keyInfoPath,
            '-f', 'hls',
            $playlistPath,
        ];

        $process = new Process($command);
        $process->setTimeout(3600);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('FFmpeg HLS conversion failed', [
                    'command' => implode(' ', $command),
                    'error' => $process->getErrorOutput(),
                    'exit_code' => $process->getExitCode(),
                ]);
                return false;
            }

            return file_exists($playlistPath);
        } catch (\Exception $e) {
            Log::error('FFmpeg process exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getVideoDuration(string $inputPath): ?int
    {
        // Use ffprobe for fast metadata read (no decoding needed)
        $ffprobePath = str_replace('ffmpeg', 'ffprobe', $this->ffmpegPath);
        if (file_exists($ffprobePath)) {
            $command = [
                $ffprobePath,
                '-v', 'quiet',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $inputPath,
            ];
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();
            if ($process->isSuccessful()) {
                $seconds = (float) trim($process->getOutput());
                if ($seconds > 0) {
                    return (int) round($seconds);
                }
            }
        }

        // Fallback: parse ffmpeg stderr (read header only, no decoding)
        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
        ];

        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        $output = $process->getErrorOutput();

        if (preg_match('/Duration:\s*(\d{2}):(\d{2}):(\d{2})/', $output, $matches)) {
            return ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3];
        }

        return null;
    }

    public function getVideoResolution(string $inputPath): ?string
    {
        // Use ffprobe for fast metadata read
        $ffprobePath = str_replace('ffmpeg', 'ffprobe', $this->ffmpegPath);
        if (file_exists($ffprobePath)) {
            $command = [
                $ffprobePath,
                '-v', 'quiet',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=width,height',
                '-of', 'csv=p=0',
                $inputPath,
            ];
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();
            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                if (preg_match('/(\d+),(\d+)/', $output, $matches)) {
                    $height = (int) $matches[2];
                    return match (true) {
                        $height >= 1080 => '1080p',
                        $height >= 720 => '720p',
                        $height >= 480 => '480p',
                        default => '360p',
                    };
                }
            }
        }

        // Fallback: parse ffmpeg stderr
        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
        ];

        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        $output = $process->getErrorOutput();

        if (preg_match('/(\d{3,4})x(\d{3,4})/', $output, $matches)) {
            $height = (int) $matches[2];
            return match (true) {
                $height >= 1080 => '1080p',
                $height >= 720 => '720p',
                $height >= 480 => '480p',
                default => '360p',
            };
        }

        return null;
    }
}
