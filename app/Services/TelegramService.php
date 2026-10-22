<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $baseUrl = 'https://api.telegram.org';

    /**
     * Send a text message to a Telegram channel.
     *
     * @param  string  $botToken   The Bot API token (e.g. "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11")
     * @param  string  $channelId  The channel username (@channelname) or numeric chat ID
     * @param  string  $text       The message text (supports HTML parse mode)
     * @return array{success: bool, message_id?: int, error?: string}
     */
    public function sendMessage(string $botToken, string $channelId, string $text): array
    {
        try {
            $response = Http::post("{$this->baseUrl}/bot{$botToken}/sendMessage", [
                'chat_id'    => $channelId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success'    => true,
                    'message_id' => $response->json('result.message_id'),
                ];
            }

            $error = $response->json('description') ?? 'Unknown Telegram API error';
            Log::error('Telegram sendMessage failed: ' . $error, [
                'channel_id' => $channelId,
                'status'     => $response->status(),
            ]);

            return ['success' => false, 'error' => $error];
        } catch (\Exception $e) {
            Log::error('Telegram sendMessage exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a photo with an optional caption to a Telegram channel.
     *
     * @param  string       $botToken   The Bot API token
     * @param  string       $channelId  The channel username or numeric chat ID
     * @param  string       $photoUrl   Public URL of the image
     * @param  string|null  $caption    Optional caption (supports HTML)
     * @return array{success: bool, message_id?: int, error?: string}
     */
    public function sendPhoto(string $botToken, string $channelId, string $photoUrl, ?string $caption = null): array
    {
        try {
            $params = [
                'chat_id'    => $channelId,
                'photo'      => $photoUrl,
                'parse_mode' => 'HTML',
            ];

            if ($caption) {
                $params['caption'] = $caption;
            }

            $response = Http::post("{$this->baseUrl}/bot{$botToken}/sendPhoto", $params);

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success'    => true,
                    'message_id' => $response->json('result.message_id'),
                ];
            }

            $error = $response->json('description') ?? 'Unknown Telegram API error';
            Log::error('Telegram sendPhoto failed: ' . $error, [
                'channel_id' => $channelId,
                'photo_url'  => $photoUrl,
                'status'     => $response->status(),
            ]);

            return ['success' => false, 'error' => $error];
        } catch (\Exception $e) {
            Log::error('Telegram sendPhoto exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a video with an optional caption to a Telegram channel.
     *
     * @param  string       $botToken   The Bot API token
     * @param  string       $channelId  The channel username or numeric chat ID
     * @param  string       $videoUrl   Public URL of the video
     * @param  string|null  $caption    Optional caption (supports HTML)
     * @return array{success: bool, message_id?: int, error?: string}
     */
    public function sendVideo(string $botToken, string $channelId, string $videoUrl, ?string $caption = null): array
    {
        try {
            $params = [
                'chat_id'    => $channelId,
                'video'      => $videoUrl,
                'parse_mode' => 'HTML',
            ];

            if ($caption) {
                $params['caption'] = $caption;
            }

            $response = Http::timeout(120)->post("{$this->baseUrl}/bot{$botToken}/sendVideo", $params);

            if ($response->successful() && $response->json('ok')) {
                return [
                    'success'    => true,
                    'message_id' => $response->json('result.message_id'),
                ];
            }

            $error = $response->json('description') ?? 'Unknown Telegram API error';
            Log::error('Telegram sendVideo failed: ' . $error, [
                'channel_id' => $channelId,
                'status'     => $response->status(),
            ]);

            return ['success' => false, 'error' => $error];
        } catch (\Exception $e) {
            Log::error('Telegram sendVideo exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * High-level method: publish a post (text + optional media) to a Telegram channel.
     * Automatically picks sendMessage, sendPhoto, or sendVideo based on media type.
     *
     * @param  string       $botToken   The Bot API token
     * @param  string       $channelId  The channel username or numeric chat ID
     * @param  string       $text       The post content
     * @param  string|null  $mediaUrl   Optional media URL
     * @param  string       $mediaType  'image', 'video', or null for text-only
     * @return array{success: bool, message_id?: int, error?: string}
     */
    public function publishPost(string $botToken, string $channelId, string $text, ?string $mediaUrl = null, string $mediaType = 'image'): array
    {
        if ($mediaUrl) {
            if ($mediaType === 'video') {
                return $this->sendVideo($botToken, $channelId, $mediaUrl, $text);
            }
            return $this->sendPhoto($botToken, $channelId, $mediaUrl, $text);
        }

        return $this->sendMessage($botToken, $channelId, $text);
    }

    /**
     * Verify a bot token is valid by calling the getMe endpoint.
     *
     * @param  string  $botToken
     * @return array{valid: bool, bot_name?: string, error?: string}
     */
    public function verifyBot(string $botToken): array
    {
        try {
            $response = Http::get("{$this->baseUrl}/bot{$botToken}/getMe");

            if ($response->successful() && $response->json('ok')) {
                return [
                    'valid'    => true,
                    'bot_name' => $response->json('result.first_name'),
                ];
            }

            return ['valid' => false, 'error' => $response->json('description') ?? 'Invalid bot token'];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
