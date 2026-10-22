<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HiringApiService — Hiring site REST API client
 *
 * Wraps the my-communication-hiring.com public API.
 * All read endpoints are public (no auth required).
 */
class HiringApiService
{
    private const CACHE_TTL = 60;
    private const TIMEOUT   = 10;

    private function baseUrl(): string
    {
        return rtrim(env('HIRING_API_BASE_URL', 'https://my-communication-hiring.com/api/v1'), '/');
    }

    public function isConfigured(): bool
    {
        return (bool) env('HIRING_API_BASE_URL');
    }

    private function get(string $endpoint, array $params = []): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->get($this->baseUrl() . '/' . ltrim($endpoint, '/'), $params);

            if ($response->successful()) {
                $json = $response->json();
                if (isset($json['error']) && $json['error'] === false) {
                    return $json['data'] ?? [];
                }
                return $json;
            }

            Log::warning('HiringApi error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => substr($response->body(), 0, 300),
            ]);
        } catch (\Exception $e) {
            Log::error('HiringApi exception: ' . $e->getMessage());
        }

        return null;
    }

    // ================================================================
    // Jobs
    // ================================================================

    public function getJobs(int $page = 1, ?int $companyId = null): array
    {
        $params = ['page' => $page];
        if ($companyId) $params['company_id'] = $companyId;

        $key = 'hiring_jobs_' . md5(json_encode($params));
        return Cache::remember($key, self::CACHE_TTL, function () use ($params) {
            return $this->get('jobs', $params) ?? [];
        });
    }

    // ================================================================
    // Blog Posts
    // ================================================================

    public function getPosts(int $page = 1, int $perPage = 10): array
    {
        $params = ['page' => $page, 'per_page' => $perPage];

        $key = 'hiring_posts_' . md5(json_encode($params));
        return Cache::remember($key, self::CACHE_TTL, function () use ($params) {
            return $this->get('posts', $params) ?? [];
        });
    }

    public function getPost(string $slug): ?array
    {
        $key = 'hiring_post_' . $slug;
        return Cache::remember($key, self::CACHE_TTL, function () use ($slug) {
            return $this->get('posts/' . $slug);
        });
    }

    public function filterPosts(array $filters = []): array
    {
        $key = 'hiring_posts_filter_' . md5(json_encode($filters));
        return Cache::remember($key, self::CACHE_TTL, function () use ($filters) {
            return $this->get('posts/filters', $filters) ?? [];
        });
    }

    public function searchPosts(string $query): array
    {
        $key = 'hiring_search_' . md5($query);
        return Cache::remember($key, self::CACHE_TTL, function () use ($query) {
            return $this->get('search', ['q' => $query]) ?? [];
        });
    }

    // ================================================================
    // Categories & Tags
    // ================================================================

    public function getCategories(int $page = 1, int $perPage = 50): array
    {
        $key = 'hiring_categories_' . $page;
        return Cache::remember($key, self::CACHE_TTL, function () use ($page, $perPage) {
            return $this->get('categories', ['page' => $page, 'per_page' => $perPage]) ?? [];
        });
    }

    public function getTags(int $page = 1, int $perPage = 50): array
    {
        $key = 'hiring_tags_' . $page;
        return Cache::remember($key, self::CACHE_TTL, function () use ($page, $perPage) {
            return $this->get('tags', ['page' => $page, 'per_page' => $perPage]) ?? [];
        });
    }

    // ================================================================
    // Languages
    // ================================================================

    public function getLanguages(): array
    {
        return Cache::remember('hiring_languages', 300, function () {
            return $this->get('languages') ?? [];
        });
    }
}
