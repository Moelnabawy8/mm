<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CareerPathService — Supabase Edge Functions client
 *
 * Wraps the Career Path Analyzer admin API hosted on Supabase.
 * Handles auto-login (JWT retrieval + caching) and token refresh on 401.
 */
class CareerPathService
{
    private const CACHE_TTL      = 60;
    private const TOKEN_TTL      = 3500; // ~58 min (Supabase tokens last 1h)
    private const TIMEOUT        = 15;
    private const TOKEN_CACHE_KEY = 'supabase_career_jwt';

    private function baseUrl(): string
    {
        return rtrim(env('SUPABASE_URL', ''), '/');
    }

    private function functionsUrl(): string
    {
        return $this->baseUrl() . '/functions/v1';
    }

    public function isConfigured(): bool
    {
        return env('SUPABASE_URL')
            && env('SUPABASE_ANON_KEY')
            && env('SUPABASE_ADMIN_EMAIL')
            && env('SUPABASE_ADMIN_PASSWORD');
    }

    // ================================================================
    // Authentication — auto-login with caching
    // ================================================================

    private function getToken(bool $forceRefresh = false): ?string
    {
        if (!$forceRefresh) {
            $cached = Cache::get(self::TOKEN_CACHE_KEY);
            if ($cached) return $cached;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'apikey'       => env('SUPABASE_ANON_KEY'),
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl() . '/auth/v1/token?grant_type=password', [
                    'email'    => env('SUPABASE_ADMIN_EMAIL'),
                    'password' => env('SUPABASE_ADMIN_PASSWORD'),
                ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                if ($token) {
                    Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL);
                    return $token;
                }
            }

            Log::warning('CareerPath: Supabase login failed', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 300),
            ]);
        } catch (\Exception $e) {
            Log::error('CareerPath: Supabase login exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Authenticated GET request with auto-retry on 401.
     */
    private function authGet(string $endpoint, array $params = []): ?array
    {
        $token = $this->getToken();
        if (!$token) return null;

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withToken($token)
                ->withHeaders(['apikey' => env('SUPABASE_ANON_KEY')])
                ->get($this->functionsUrl() . '/' . ltrim($endpoint, '/'), $params);

            // Token expired — refresh once and retry
            if ($response->status() === 401) {
                $token = $this->getToken(true);
                if (!$token) return null;

                $response = Http::timeout(self::TIMEOUT)
                    ->withToken($token)
                    ->withHeaders(['apikey' => env('SUPABASE_ANON_KEY')])
                    ->get($this->functionsUrl() . '/' . ltrim($endpoint, '/'), $params);
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::warning('CareerPath API error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => substr($response->body(), 0, 300),
            ]);
        } catch (\Exception $e) {
            Log::error('CareerPath API exception: ' . $e->getMessage());
        }

        return null;
    }

    // ================================================================
    // Public methods
    // ================================================================

    public function getStats(): array
    {
        return Cache::remember('career_stats', self::CACHE_TTL, function () {
            return $this->authGet('admin-stats') ?? [
                'totalUsers'         => 0,
                'totalAssessments'   => 0,
                'totalCVs'           => 0,
                'totalTokensUsed'    => 0,
                'activeUsers'        => 0,
                'assessmentsByTrack' => [],
            ];
        });
    }

    public function getUsers(int $page = 1, int $limit = 20, string $search = ''): array
    {
        $params = ['page' => $page, 'limit' => $limit];
        if ($search) $params['search'] = $search;

        $key = 'career_users_' . md5(json_encode($params));
        return Cache::remember($key, self::CACHE_TTL, function () use ($params) {
            $data = $this->authGet('admin-users', $params);
            return $data ?? ['users' => [], 'total' => 0, 'page' => 1, 'limit' => 20];
        });
    }

    public function getAssessments(int $page = 1, int $limit = 20, string $track = ''): array
    {
        $params = ['page' => $page, 'limit' => $limit];
        if ($track) $params['track'] = $track;

        $key = 'career_assessments_' . md5(json_encode($params));
        return Cache::remember($key, self::CACHE_TTL, function () use ($params) {
            $data = $this->authGet('admin-assessments', $params);
            return $data ?? ['assessments' => [], 'total' => 0, 'page' => 1, 'limit' => 20];
        });
    }

    public function getCvs(int $page = 1, int $limit = 20): array
    {
        $params = ['page' => $page, 'limit' => $limit];

        $key = 'career_cvs_' . md5(json_encode($params));
        return Cache::remember($key, self::CACHE_TTL, function () use ($params) {
            $data = $this->authGet('admin-cvs', $params);
            return $data ?? ['cvs' => [], 'total' => 0, 'page' => 1, 'limit' => 20];
        });
    }

    public function getExportUrl(string $type): ?string
    {
        $token = $this->getToken();
        if (!$token) return null;

        return $this->functionsUrl() . '/export-excel?type=' . urlencode($type);
    }

    public function getExportHeaders(): array
    {
        $token = $this->getToken();
        return $token ? ['Authorization' => 'Bearer ' . $token, 'apikey' => env('SUPABASE_ANON_KEY')] : [];
    }
}
