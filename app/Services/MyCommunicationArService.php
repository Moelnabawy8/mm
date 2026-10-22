<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MyCommunicationArService — Phase 1.5 + Write ops
 *
 * Server-side client for the Arabic site (my-communication.com).
 * Wraps WordPress + WooCommerce + LearnPress REST APIs.
 *
 * Conventions:
 *   - Every read method: cached (60s) + try/catch + safe fallback (never throws)
 *   - Every write method: returns ['ok'=>bool, 'data'=>..., 'error'=>...]
 *     and logs failures so the controller can surface a flash message.
 */
class MyCommunicationArService
{
    private const SITE_URL = 'https://my-communication.com';
    private const CACHE_TTL = 60;
    private const TIMEOUT = 8;

    private function wpBase(): string  { return self::SITE_URL . '/wp-json/wp/v2'; }
    private function wcBase(): string  { return self::SITE_URL . '/wp-json/wc/v3'; }
    private function lpBase(): string  { return self::SITE_URL . '/wp-json/lp/v1'; }

    private function wcAuth(): array
    {
        return [
            'consumer_key'    => env('WOOCOMMERCE_AR_CONSUMER_KEY', ''),
            'consumer_secret' => env('WOOCOMMERCE_AR_CONSUMER_SECRET', ''),
        ];
    }

    public function isConfigured(): bool
    {
        return env('WOOCOMMERCE_AR_CONSUMER_KEY') && env('WOOCOMMERCE_AR_CONSUMER_SECRET');
    }

    // ================================================================
    // WordPress (public reads)
    // ================================================================

    public function getPosts(int $perPage = 10, int $page = 1, string $search = '', int $categoryId = 0): array
    {
        $key = "mc_ar_posts_{$perPage}_{$page}_" . md5($search . $categoryId);
        return Cache::remember($key, self::CACHE_TTL, function () use ($perPage, $page, $search, $categoryId) {
            try {
                $params = [
                    'per_page' => $perPage,
                    'page'     => $page,
                    '_embed'   => 'author,wp:term,wp:featuredmedia',
                    'orderby'  => 'date',
                    'order'    => 'desc',
                ];
                if ($search) $params['search'] = $search;
                if ($categoryId) $params['categories'] = $categoryId;
                $r = Http::timeout(self::TIMEOUT)->get($this->wpBase() . '/posts', $params);
                if (!$r->successful()) return ['items' => [], 'total' => 0, 'pages' => 0];
                return [
                    'items' => array_map(fn($p) => $this->trimPost($p), $r->json() ?: []),
                    'total' => (int) ($r->header('X-WP-Total') ?: 0),
                    'pages' => (int) ($r->header('X-WP-TotalPages') ?: 1),
                ];
            } catch (\Throwable $e) {
                Log::warning('MC-AR getPosts failed', ['err' => $e->getMessage()]);
                return ['items' => [], 'total' => 0, 'pages' => 0];
            }
        });
    }

    public function getCategories(int $perPage = 50): array
    {
        return Cache::remember("mc_ar_cats_{$perPage}", self::CACHE_TTL, function () use ($perPage) {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wpBase() . '/categories', [
                    'per_page' => $perPage, 'orderby' => 'count', 'order' => 'desc',
                ]);
                if (!$r->successful()) return [];
                return array_map(fn($c) => [
                    'id' => $c['id'] ?? 0, 'name' => $c['name'] ?? '',
                    'slug' => $c['slug'] ?? '', 'count' => $c['count'] ?? 0,
                    'link' => $c['link'] ?? '',
                ], $r->json() ?: []);
            } catch (\Throwable $e) {
                Log::warning('MC-AR getCategories failed', ['err' => $e->getMessage()]);
                return [];
            }
        });
    }

    private function trimPost(array $p): array
    {
        $author = $p['_embedded']['author'][0] ?? null;
        $cats = []; $tags = [];
        if (!empty($p['_embedded']['wp:term'])) {
            foreach ($p['_embedded']['wp:term'] as $group) {
                foreach ($group as $term) {
                    if (($term['taxonomy'] ?? '') === 'category')   $cats[] = $term['name'] ?? '';
                    elseif (($term['taxonomy'] ?? '') === 'post_tag') $tags[] = $term['name'] ?? '';
                }
            }
        }
        $featured = $p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null;
        return [
            'id' => $p['id'] ?? 0,
            'title' => strip_tags($p['title']['rendered'] ?? ''),
            'slug' => $p['slug'] ?? '', 'link' => $p['link'] ?? '',
            'date' => $p['date'] ?? '',
            'excerpt' => trim(strip_tags($p['excerpt']['rendered'] ?? '')),
            'author' => $author['name'] ?? '—',
            'categories' => $cats, 'tags' => $tags, 'featured' => $featured,
        ];
    }

    // ================================================================
    // WooCommerce — Customers
    // ================================================================

    public function getUsers(int $perPage = 20, int $page = 1, string $search = ''): array
    {
        if (!$this->isConfigured()) return ['items' => [], 'total' => 0, 'pages' => 0];
        $key = "mc_ar_users_{$perPage}_{$page}_" . md5($search);
        return Cache::remember($key, self::CACHE_TTL, function () use ($perPage, $page, $search) {
            try {
                $params = array_merge($this->wcAuth(), [
                    'per_page' => $perPage, 'page' => $page,
                    'orderby' => 'registered_date', 'order' => 'desc',
                    'role' => 'customer',
                ]);
                if ($search) $params['search'] = $search;
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/customers', $params);
                if (!$r->successful()) return ['items' => [], 'total' => 0, 'pages' => 0];
                return [
                    'items' => array_map(fn($u) => $this->trimCustomer($u), $r->json() ?: []),
                    'total' => (int) ($r->header('X-WP-Total') ?: 0),
                    'pages' => (int) ($r->header('X-WP-TotalPages') ?: 1),
                ];
            } catch (\Throwable $e) {
                Log::warning('MC-AR getUsers failed', ['err' => $e->getMessage()]);
                return ['items' => [], 'total' => 0, 'pages' => 0];
            }
        });
    }

    private function trimCustomer(array $u): array
    {
        return [
            'id' => $u['id'] ?? 0,
            'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? ''),
            'username' => $u['username'] ?? '',
            'email' => $u['email'] ?? '',
            'avatar' => $u['avatar_url'] ?? '',
            'date_created' => $u['date_created'] ?? '',
            'orders_count' => (int) ($u['orders_count'] ?? 0),
            'total_spent' => (float) ($u['total_spent'] ?? 0),
            'is_paying' => (bool) ($u['is_paying_customer'] ?? false),
            'phone' => $u['billing']['phone'] ?? '',
            'country' => $u['billing']['country'] ?? '',
            'city' => $u['billing']['city'] ?? '',
        ];
    }

    public function getCustomerCount(): int
    {
        if (!$this->isConfigured()) return 0;
        return Cache::remember('mc_ar_customer_count', self::CACHE_TTL, function () {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/customers', array_merge($this->wcAuth(), [
                    'per_page' => 1, 'role' => 'customer',
                ]));
                return $r->successful() ? (int) ($r->header('X-WP-Total') ?: 0) : 0;
            } catch (\Throwable $e) { return 0; }
        });
    }

    public function getCustomerDetail(int $id): ?array
    {
        if (!$this->isConfigured()) return null;
        return Cache::remember("mc_ar_customer_{$id}", self::CACHE_TTL, function () use ($id) {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . "/customers/{$id}", $this->wcAuth());
                if (!$r->successful()) return null;
                $u = $r->json() ?: [];
                return [
                    'id' => $u['id'] ?? 0,
                    'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? ''),
                    'username' => $u['username'] ?? '',
                    'email' => $u['email'] ?? '',
                    'avatar' => $u['avatar_url'] ?? '',
                    'date_created' => $u['date_created'] ?? '',
                    'orders_count' => (int) ($u['orders_count'] ?? 0),
                    'total_spent' => (float) ($u['total_spent'] ?? 0),
                    'is_paying' => (bool) ($u['is_paying_customer'] ?? false),
                    'billing' => $u['billing'] ?? [],
                    'shipping' => $u['shipping'] ?? [],
                    'role' => $u['role'] ?? '',
                ];
            } catch (\Throwable $e) {
                Log::warning('MC-AR getCustomerDetail failed', ['id' => $id, 'err' => $e->getMessage()]);
                return null;
            }
        });
    }

    /** Top customers by total_spent — uses the WC customers endpoint with desc sort. */
    public function getTopCustomers(int $limit = 10): array
    {
        if (!$this->isConfigured()) return [];
        return Cache::remember("mc_ar_top_customers_{$limit}", 300, function () use ($limit) {
            try {
                // WC doesn't sort customers by total_spent natively, so we fetch a
                // wider slice and sort in PHP. 100 is the WC max per page.
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/customers', array_merge($this->wcAuth(), [
                    'per_page' => 100, 'role' => 'customer', 'orderby' => 'registered_date', 'order' => 'desc',
                ]));
                if (!$r->successful()) return [];
                $customers = array_map(fn($u) => $this->trimCustomer($u), $r->json() ?: []);
                usort($customers, fn($a, $b) => $b['total_spent'] <=> $a['total_spent']);
                return array_slice($customers, 0, $limit);
            } catch (\Throwable $e) { return []; }
        });
    }

    // ================================================================
    // WooCommerce — Orders
    // ================================================================

    public function getOrders(int $perPage = 20, int $page = 1, string $status = '', string $search = ''): array
    {
        if (!$this->isConfigured()) return ['items' => [], 'total' => 0, 'pages' => 0];
        $key = "mc_ar_orders_{$perPage}_{$page}_" . md5($status . $search);
        return Cache::remember($key, self::CACHE_TTL, function () use ($perPage, $page, $status, $search) {
            try {
                $params = array_merge($this->wcAuth(), [
                    'per_page' => $perPage, 'page' => $page,
                    'orderby' => 'date', 'order' => 'desc',
                ]);
                if ($status) $params['status'] = $status;
                if ($search) $params['search'] = $search;
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/orders', $params);
                if (!$r->successful()) return ['items' => [], 'total' => 0, 'pages' => 0];
                return [
                    'items' => array_map(fn($o) => $this->trimOrder($o), $r->json() ?: []),
                    'total' => (int) ($r->header('X-WP-Total') ?: 0),
                    'pages' => (int) ($r->header('X-WP-TotalPages') ?: 1),
                ];
            } catch (\Throwable $e) {
                Log::warning('MC-AR getOrders failed', ['err' => $e->getMessage()]);
                return ['items' => [], 'total' => 0, 'pages' => 0];
            }
        });
    }

    private function trimOrder(array $o): array
    {
        return [
            'id' => $o['id'] ?? 0,
            'number' => $o['number'] ?? '',
            'status' => $o['status'] ?? '',
            'currency' => $o['currency'] ?? 'USD',
            'total' => (float) ($o['total'] ?? 0),
            'subtotal' => (float) ($o['subtotal'] ?? 0),
            'discount_total' => (float) ($o['discount_total'] ?? 0),
            'shipping_total' => (float) ($o['shipping_total'] ?? 0),
            'date_created' => $o['date_created'] ?? '',
            'date_modified' => $o['date_modified'] ?? '',
            'customer_id' => $o['customer_id'] ?? 0,
            'customer_name' => trim(($o['billing']['first_name'] ?? '') . ' ' . ($o['billing']['last_name'] ?? '')),
            'customer_email' => $o['billing']['email'] ?? '',
            'customer_phone' => $o['billing']['phone'] ?? '',
            'items_count' => is_array($o['line_items'] ?? null) ? count($o['line_items']) : 0,
            'payment_method' => $o['payment_method_title'] ?? '',
        ];
    }

    public function getOrderStats(): array
    {
        if (!$this->isConfigured()) return ['pending' => 0, 'processing' => 0, 'completed' => 0, 'refunded' => 0, 'on-hold' => 0, 'cancelled' => 0, 'total' => 0];
        return Cache::remember('mc_ar_order_stats', self::CACHE_TTL, function () {
            $stats = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'refunded' => 0, 'on-hold' => 0, 'cancelled' => 0, 'total' => 0];
            foreach (['pending', 'processing', 'completed', 'refunded', 'on-hold', 'cancelled'] as $status) {
                try {
                    $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/orders', array_merge($this->wcAuth(), [
                        'status' => $status, 'per_page' => 1,
                    ]));
                    if ($r->successful()) $stats[$status] = (int) ($r->header('X-WP-Total') ?: 0);
                } catch (\Throwable $e) { /* ignore */ }
            }
            $stats['total'] = $stats['pending'] + $stats['processing'] + $stats['completed'] + $stats['refunded'] + $stats['on-hold'] + $stats['cancelled'];
            return $stats;
        });
    }

    public function getOrderDetail(int $id): ?array
    {
        if (!$this->isConfigured()) return null;
        return Cache::remember("mc_ar_order_{$id}", self::CACHE_TTL, function () use ($id) {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . "/orders/{$id}", $this->wcAuth());
                if (!$r->successful()) return null;
                $o = $r->json() ?: [];
                $base = $this->trimOrder($o);
                $base['line_items'] = array_map(fn($li) => [
                    'name' => $li['name'] ?? '',
                    'quantity' => $li['quantity'] ?? 0,
                    'subtotal' => (float) ($li['subtotal'] ?? 0),
                    'total' => (float) ($li['total'] ?? 0),
                    'price' => (float) ($li['price'] ?? 0),
                    'product_id' => $li['product_id'] ?? 0,
                ], $o['line_items'] ?? []);
                $base['billing'] = $o['billing'] ?? [];
                $base['shipping'] = $o['shipping'] ?? [];
                $base['customer_note'] = $o['customer_note'] ?? '';
                $base['transaction_id'] = $o['transaction_id'] ?? '';
                return $base;
            } catch (\Throwable $e) {
                Log::warning('MC-AR getOrderDetail failed', ['id' => $id, 'err' => $e->getMessage()]);
                return null;
            }
        });
    }

    public function getOrderNotes(int $id): array
    {
        if (!$this->isConfigured()) return [];
        return Cache::remember("mc_ar_order_notes_{$id}", 30, function () use ($id) {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . "/orders/{$id}/notes", $this->wcAuth());
                if (!$r->successful()) return [];
                return array_map(fn($n) => [
                    'id' => $n['id'] ?? 0,
                    'author' => $n['author'] ?? '',
                    'note' => strip_tags($n['note'] ?? ''),
                    'date_created' => $n['date_created'] ?? '',
                    'customer_note' => (bool) ($n['customer_note'] ?? false),
                ], $r->json() ?: []);
            } catch (\Throwable $e) { return []; }
        });
    }

    // ================================================================
    // Charts data — timelines
    // ================================================================

    /** Aggregates orders by day for the last $days days. */
    public function getOrdersTimeline(int $days = 30): array
    {
        if (!$this->isConfigured()) return ['labels' => [], 'orders' => [], 'revenue' => []];
        return Cache::remember("mc_ar_timeline_{$days}", 300, function () use ($days) {
            try {
                $after = now()->subDays($days)->startOfDay()->toIso8601String();
                $allOrders = [];
                $page = 1;
                do {
                    $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/orders', array_merge($this->wcAuth(), [
                        'per_page' => 100, 'page' => $page, 'after' => $after,
                        'orderby' => 'date', 'order' => 'asc',
                    ]));
                    if (!$r->successful()) break;
                    $batch = $r->json() ?: [];
                    foreach ($batch as $o) {
                        $allOrders[] = ['date' => $o['date_created'] ?? '', 'total' => (float) ($o['total'] ?? 0)];
                    }
                    $totalPages = (int) ($r->header('X-WP-TotalPages') ?: 1);
                    if ($page >= $totalPages || count($allOrders) >= 1000) break;
                    $page++;
                } while ($page <= 10);

                // Bucket by day
                $buckets = [];
                for ($i = $days; $i >= 0; $i--) {
                    $d = now()->subDays($i)->format('Y-m-d');
                    $buckets[$d] = ['orders' => 0, 'revenue' => 0.0];
                }
                foreach ($allOrders as $o) {
                    $d = substr($o['date'], 0, 10);
                    if (isset($buckets[$d])) {
                        $buckets[$d]['orders']++;
                        $buckets[$d]['revenue'] += $o['total'];
                    }
                }
                return [
                    'labels' => array_keys($buckets),
                    'orders' => array_values(array_map(fn($b) => $b['orders'], $buckets)),
                    'revenue' => array_values(array_map(fn($b) => round($b['revenue'], 2), $buckets)),
                ];
            } catch (\Throwable $e) {
                Log::warning('MC-AR getOrdersTimeline failed', ['err' => $e->getMessage()]);
                return ['labels' => [], 'orders' => [], 'revenue' => []];
            }
        });
    }

    /** Cart abandonment: orders stuck in 'pending' longer than 24 hours. */
    public function getAbandonedOrders(int $hoursAgo = 24): array
    {
        if (!$this->isConfigured()) return [];
        return Cache::remember("mc_ar_abandoned_{$hoursAgo}", self::CACHE_TTL, function () use ($hoursAgo) {
            try {
                $before = now()->subHours($hoursAgo)->toIso8601String();
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/orders', array_merge($this->wcAuth(), [
                    'status' => 'pending', 'per_page' => 50, 'before' => $before,
                    'orderby' => 'date', 'order' => 'desc',
                ]));
                if (!$r->successful()) return [];
                return array_map(fn($o) => $this->trimOrder($o), $r->json() ?: []);
            } catch (\Throwable $e) { return []; }
        });
    }

    /** Activity feed: latest orders + customers + posts merged into one timeline. */
    public function getActivityFeed(int $limit = 30): array
    {
        $events = [];
        try {
            // Latest 10 orders
            $orders = $this->getOrders(10, 1)['items'] ?? [];
            foreach ($orders as $o) {
                $events[] = [
                    'type' => 'order', 'icon' => 'shopping-cart', 'color' => 'success',
                    'title' => __('New Order') . ' #' . $o['number'],
                    'subtitle' => ($o['customer_name'] ?: 'Guest') . ' · ' . number_format($o['total'], 2) . ' ' . $o['currency'],
                    'badge' => $o['status'],
                    'date' => $o['date_created'],
                ];
            }
            // Latest 10 customers
            $custs = $this->getUsers(10, 1)['items'] ?? [];
            foreach ($custs as $c) {
                $events[] = [
                    'type' => 'customer', 'icon' => 'user-plus', 'color' => 'primary',
                    'title' => __('New Customer'),
                    'subtitle' => ($c['name'] ?: 'No name') . ' · ' . $c['email'],
                    'badge' => $c['is_paying'] ? 'paying' : 'lead',
                    'date' => $c['date_created'],
                ];
            }
            // Latest 5 posts
            $posts = $this->getPosts(5, 1)['items'] ?? [];
            foreach ($posts as $p) {
                $events[] = [
                    'type' => 'post', 'icon' => 'article', 'color' => 'info',
                    'title' => __('New Blog Post'),
                    'subtitle' => $p['title'],
                    'badge' => $p['author'],
                    'date' => $p['date'],
                ];
            }
            // Sort all events by date desc
            usort($events, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
            return array_slice($events, 0, $limit);
        } catch (\Throwable $e) {
            Log::warning('MC-AR activity feed failed', ['err' => $e->getMessage()]);
            return [];
        }
    }

    public function getProductCount(): int
    {
        if (!$this->isConfigured()) return 0;
        return Cache::remember('mc_ar_product_count', self::CACHE_TTL, function () {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/products', array_merge($this->wcAuth(), ['per_page' => 1]));
                return $r->successful() ? (int) ($r->header('X-WP-Total') ?: 0) : 0;
            } catch (\Throwable $e) { return 0; }
        });
    }

    public function getTopProducts(int $limit = 5): array
    {
        if (!$this->isConfigured()) return [];
        return Cache::remember("mc_ar_top_products_{$limit}", 300, function () use ($limit) {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->wcBase() . '/products', array_merge($this->wcAuth(), [
                    'per_page' => $limit, 'orderby' => 'popularity',
                ]));
                if (!$r->successful()) return [];
                return array_map(fn($p) => [
                    'id' => $p['id'] ?? 0,
                    'name' => $p['name'] ?? '',
                    'price' => $p['price'] ?? '',
                    'total_sales' => (int) ($p['total_sales'] ?? 0),
                    'image' => ($p['images'][0]['src'] ?? ''),
                    'permalink' => $p['permalink'] ?? '',
                ], $r->json() ?: []);
            } catch (\Throwable $e) { return []; }
        });
    }

    // ================================================================
    // LearnPress
    // ================================================================

    public function getCourses(int $perPage = 100): array
    {
        return Cache::remember("mc_ar_courses_{$perPage}", self::CACHE_TTL, function () use ($perPage) {
            try {
                $r = Http::timeout(self::TIMEOUT)->get($this->lpBase() . '/courses', ['per_page' => $perPage]);
                if (!$r->successful()) return [];
                $body = $r->json();
                $list = $body['data']['courses'] ?? [];
                return is_array($list) ? array_map(fn($c) => $this->trimCourse($c), $list) : [];
            } catch (\Throwable $e) { return []; }
        });
    }

    private function trimCourse(array $c): array
    {
        $image = '';
        if (!empty($c['image']) && preg_match('/src=["\']([^"\']+)["\']/', $c['image'], $m)) $image = $m[1];
        return [
            'id' => (int) ($c['ID'] ?? 0),
            'name' => trim(strip_tags($c['title'] ?? '')),
            'description' => trim(strip_tags($c['description'] ?? '')),
            'price' => trim(html_entity_decode(strip_tags($c['price'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'students_count' => (int) preg_replace('/\D/', '', strip_tags($c['student'] ?? '0')),
            'lessons_count' => (int) preg_replace('/\D/', '', strip_tags($c['lesson'] ?? '0')),
            'quizzes_count' => (int) preg_replace('/\D/', '', strip_tags($c['quiz'] ?? '0')),
            'duration' => trim(strip_tags($c['duration'] ?? '')),
            'level' => trim(strip_tags($c['level'] ?? '')),
            'instructor' => trim(strip_tags($c['instructor'] ?? '—')),
            'categories' => trim(strip_tags(str_replace(',', ', ', $c['category'] ?? ''))),
            'image' => $image,
            'permalink' => self::SITE_URL . '/courses/?p=' . ($c['ID'] ?? ''),
        ];
    }

    public function getCourseCount(): int
    {
        return Cache::remember('mc_ar_course_count', self::CACHE_TTL, function () {
            return count($this->getCourses(100));
        });
    }

    public function getMostEnrolledCourses(int $limit = 5): array
    {
        $courses = $this->getCourses(100);
        usort($courses, fn($a, $b) => $b['students_count'] <=> $a['students_count']);
        return array_slice($courses, 0, $limit);
    }

    // ================================================================
    // Aggregate
    // ================================================================

    public function getDashboardKpis(): array
    {
        return [
            'users' => $this->getCustomerCount(),
            'orders' => $this->getOrderStats(),
            'courses' => $this->getCourseCount(),
            'products' => $this->getProductCount(),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    // ================================================================
    // WRITE OPERATIONS — return ['ok'=>bool, 'data'=>?array, 'error'=>?string]
    // ================================================================

    public function updateOrderStatus(int $orderId, string $newStatus): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'API not configured'];
        $allowed = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'];
        if (!in_array($newStatus, $allowed)) return ['ok' => false, 'error' => 'Invalid status'];
        try {
            $r = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->put($this->wcBase() . "/orders/{$orderId}?" . http_build_query($this->wcAuth()), [
                    'status' => $newStatus,
                ]);
            $this->forgetOrderCache($orderId);
            if ($r->successful()) {
                Log::info('MC-AR order status updated', ['id' => $orderId, 'status' => $newStatus, 'by' => auth()->id()]);
                return ['ok' => true, 'data' => $r->json()];
            }
            return ['ok' => false, 'error' => 'API returned ' . $r->status()];
        } catch (\Throwable $e) {
            Log::error('MC-AR updateOrderStatus failed', ['id' => $orderId, 'err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function addOrderNote(int $orderId, string $note, bool $customerVisible = false): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'API not configured'];
        if (trim($note) === '') return ['ok' => false, 'error' => 'Note cannot be empty'];
        try {
            $r = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->wcBase() . "/orders/{$orderId}/notes?" . http_build_query($this->wcAuth()), [
                    'note' => $note,
                    'customer_note' => $customerVisible,
                ]);
            Cache::forget("mc_ar_order_notes_{$orderId}");
            if ($r->successful()) {
                Log::info('MC-AR order note added', ['id' => $orderId, 'by' => auth()->id()]);
                return ['ok' => true, 'data' => $r->json()];
            }
            return ['ok' => false, 'error' => 'API returned ' . $r->status()];
        } catch (\Throwable $e) {
            Log::error('MC-AR addOrderNote failed', ['id' => $orderId, 'err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function refundOrder(int $orderId, float $amount, string $reason = ''): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'API not configured'];
        if ($amount <= 0) return ['ok' => false, 'error' => 'Amount must be positive'];
        try {
            $r = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->wcBase() . "/orders/{$orderId}/refunds?" . http_build_query($this->wcAuth()), [
                    'amount' => (string) $amount,
                    'reason' => $reason,
                ]);
            $this->forgetOrderCache($orderId);
            if ($r->successful()) {
                Log::warning('MC-AR order refunded', ['id' => $orderId, 'amount' => $amount, 'by' => auth()->id()]);
                return ['ok' => true, 'data' => $r->json()];
            }
            return ['ok' => false, 'error' => 'API returned ' . $r->status()];
        } catch (\Throwable $e) {
            Log::error('MC-AR refundOrder failed', ['id' => $orderId, 'err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function forgetOrderCache(int $id): void
    {
        Cache::forget("mc_ar_order_{$id}");
        Cache::forget("mc_ar_order_notes_{$id}");
        Cache::forget('mc_ar_order_stats');
        // Forget paginated list caches (rough: just bust the common ones)
        for ($p = 1; $p <= 5; $p++) {
            for ($pp = 10; $pp <= 50; $pp += 10) {
                Cache::forget("mc_ar_orders_{$pp}_{$p}_" . md5(''));
            }
        }
    }
}
