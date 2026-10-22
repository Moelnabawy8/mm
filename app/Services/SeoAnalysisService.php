<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SeoAnalysisService — Website SEO Analyzer
 *
 * Crawls a given URL and returns a comprehensive SEO audit:
 * meta tags, headings, images, links, schema, sitemap, robots.txt, SSL, performance.
 */
class SeoAnalysisService
{
    private const TIMEOUT   = 20;
    private const CACHE_TTL = 300; // 5 minutes

    private array $sites = [
        'arabic'  => ['url' => 'https://my-communication.com',  'name' => 'Arabic Website'],
        'english' => ['url' => 'https://my-communication.uk',   'name' => 'English Website'],
    ];

    public function getSiteConfig(string $site): ?array
    {
        return $this->sites[$site] ?? null;
    }

    public function getAllSites(): array
    {
        return $this->sites;
    }

    /**
     * Full SEO audit for a site. Cached for 5 minutes.
     */
    public function analyze(string $site): array
    {
        $config = $this->getSiteConfig($site);
        if (!$config) return ['error' => 'Invalid site'];

        $key = 'seo_analysis_' . $site;
        return Cache::remember($key, self::CACHE_TTL, function () use ($config, $site) {
            $url = $config['url'];
            $html = $this->fetchHtml($url);

            if (!$html) {
                return [
                    'site'       => $site,
                    'site_name'  => $config['name'],
                    'url'        => $url,
                    'error'      => 'Could not fetch website',
                    'score'      => 0,
                    'analyzed_at' => now()->toIso8601String(),
                ];
            }

            $meta       = $this->analyzeMeta($html, $url);
            $headings   = $this->analyzeHeadings($html);
            $images     = $this->analyzeImages($html);
            $links      = $this->analyzeLinks($html, $url);
            $schema     = $this->analyzeSchema($html);
            $performance = $this->analyzePerformance($url);
            $sitemap    = $this->checkSitemap($url);
            $robots     = $this->checkRobots($url);
            $ssl        = $this->checkSsl($url);
            $score      = $this->calculateScore($meta, $headings, $images, $links, $schema, $sitemap, $robots, $ssl);
            $issues     = $this->collectIssues($meta, $headings, $images, $links, $schema, $sitemap, $robots, $ssl);

            return [
                'site'        => $site,
                'site_name'   => $config['name'],
                'url'         => $url,
                'score'       => $score,
                'meta'        => $meta,
                'headings'    => $headings,
                'images'      => $images,
                'links'       => $links,
                'schema'      => $schema,
                'performance' => $performance,
                'sitemap'     => $sitemap,
                'robots'      => $robots,
                'ssl'         => $ssl,
                'issues'      => $issues,
                'analyzed_at' => now()->toIso8601String(),
                'error'       => null,
            ];
        });
    }

    /**
     * Force re-analyze (clears cache).
     */
    public function refresh(string $site): array
    {
        Cache::forget('seo_analysis_' . $site);
        return $this->analyze($site);
    }

    // ================================================================
    // HTML Fetcher
    // ================================================================

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; MyCommunicationERP/1.0; SEO Audit)',
                    'Accept'     => 'text/html',
                ])
                ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            Log::error('SEO fetch error: ' . $e->getMessage());
            return null;
        }
    }

    // ================================================================
    // Meta Analysis
    // ================================================================

    private function analyzeMeta(string $html, string $url): array
    {
        $title = $this->extractTag($html, '<title>', '</title>');
        $description = $this->extractMeta($html, 'description');
        $keywords = $this->extractMeta($html, 'keywords');
        $canonical = $this->extractLink($html, 'canonical');
        $viewport = $this->extractMeta($html, 'viewport');
        $charset = $this->extractCharset($html);
        $lang = $this->extractLang($html);

        // Open Graph
        $ogTitle = $this->extractMeta($html, 'og:title', 'property');
        $ogDesc  = $this->extractMeta($html, 'og:description', 'property');
        $ogImage = $this->extractMeta($html, 'og:image', 'property');
        $ogType  = $this->extractMeta($html, 'og:type', 'property');
        $ogUrl   = $this->extractMeta($html, 'og:url', 'property');

        // Twitter Card
        $twCard  = $this->extractMeta($html, 'twitter:card');
        $twTitle = $this->extractMeta($html, 'twitter:title');
        $twDesc  = $this->extractMeta($html, 'twitter:description');

        return [
            'title'           => $title,
            'title_length'    => mb_strlen($title ?? ''),
            'description'     => $description,
            'desc_length'     => mb_strlen($description ?? ''),
            'keywords'        => $keywords,
            'canonical'       => $canonical,
            'viewport'        => $viewport,
            'charset'         => $charset,
            'lang'            => $lang,
            'og_title'        => $ogTitle,
            'og_description'  => $ogDesc,
            'og_image'        => $ogImage,
            'og_type'         => $ogType,
            'og_url'          => $ogUrl,
            'tw_card'         => $twCard,
            'tw_title'        => $twTitle,
            'tw_description'  => $twDesc,
            'has_title'       => !empty($title),
            'has_description' => !empty($description),
            'has_viewport'    => !empty($viewport),
            'has_og'          => !empty($ogTitle),
            'has_twitter'     => !empty($twCard),
            'has_canonical'   => !empty($canonical),
        ];
    }

    // ================================================================
    // Headings Analysis
    // ================================================================

    private function analyzeHeadings(string $html): array
    {
        $result = [];
        for ($i = 1; $i <= 6; $i++) {
            preg_match_all('/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/si', $html, $matches);
            $result['h' . $i] = array_map(function ($t) {
                return trim(strip_tags($t));
            }, $matches[1] ?? []);
        }

        $result['h1_count'] = count($result['h1']);
        $result['has_h1']   = $result['h1_count'] > 0;
        $result['total']    = array_sum(array_map('count', array_values(array_filter($result, 'is_array'))));

        return $result;
    }

    // ================================================================
    // Images Analysis
    // ================================================================

    private function analyzeImages(string $html): array
    {
        preg_match_all('/<img\s[^>]*>/si', $html, $matches);
        $images = [];
        $withoutAlt = 0;
        $withoutSrc = 0;

        foreach ($matches[0] ?? [] as $tag) {
            $src = $this->extractAttr($tag, 'src');
            $alt = $this->extractAttr($tag, 'alt');
            $loading = $this->extractAttr($tag, 'loading');

            if (empty($src)) $withoutSrc++;
            if (empty($alt) && $alt !== '0') $withoutAlt++;

            $images[] = [
                'src'     => $src ? mb_substr($src, 0, 100) : null,
                'alt'     => $alt,
                'loading' => $loading,
            ];
        }

        return [
            'total'       => count($images),
            'without_alt' => $withoutAlt,
            'without_src' => $withoutSrc,
            'lazy_loaded' => count(array_filter($images, fn($i) => ($i['loading'] ?? '') === 'lazy')),
            'items'       => array_slice($images, 0, 20), // first 20 for display
        ];
    }

    // ================================================================
    // Links Analysis
    // ================================================================

    private function analyzeLinks(string $html, string $baseUrl): array
    {
        preg_match_all('/<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si', $html, $matches, PREG_SET_ORDER);

        $internal = 0;
        $external = 0;
        $nofollow = 0;
        $empty    = 0;
        $broken   = [];
        $host     = parse_url($baseUrl, PHP_URL_HOST);

        foreach ($matches as $m) {
            $href = trim($m[1] ?? '');
            $text = trim(strip_tags($m[2] ?? ''));
            $tag  = $m[0] ?? '';

            if (empty($href) || $href === '#' || str_starts_with($href, 'javascript:')) {
                $empty++;
                continue;
            }

            if (str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) continue;

            $linkHost = parse_url($href, PHP_URL_HOST);
            if (!$linkHost || $linkHost === $host) {
                $internal++;
            } else {
                $external++;
            }

            if (stripos($tag, 'nofollow') !== false) $nofollow++;
        }

        return [
            'total'    => count($matches),
            'internal' => $internal,
            'external' => $external,
            'nofollow' => $nofollow,
            'empty'    => $empty,
        ];
    }

    // ================================================================
    // Schema / Structured Data
    // ================================================================

    private function analyzeSchema(string $html): array
    {
        preg_match_all('/<script\s+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);

        $schemas = [];
        foreach ($matches[1] ?? [] as $json) {
            $decoded = json_decode(trim($json), true);
            if ($decoded) {
                $type = $decoded['@type'] ?? ($decoded[0]['@type'] ?? 'Unknown');
                $schemas[] = [
                    'type' => $type,
                    'data' => $decoded,
                ];
            }
        }

        return [
            'count'      => count($schemas),
            'has_schema' => count($schemas) > 0,
            'types'      => array_column($schemas, 'type'),
            'items'      => $schemas,
        ];
    }

    // ================================================================
    // Performance (basic timing)
    // ================================================================

    private function analyzePerformance(string $url): array
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MyCommunicationERP/1.0)'])
                ->get($url);
            $loadTime = round((microtime(true) - $start) * 1000); // ms

            $body = $response->body();
            $size = strlen($body);
            $headers = $response->headers();

            // Check compression
            $encoding = $headers['Content-Encoding'][0] ?? ($headers['content-encoding'][0] ?? '');
            $hasGzip = stripos($encoding, 'gzip') !== false || stripos($encoding, 'br') !== false;

            // Check caching
            $cacheControl = $headers['Cache-Control'][0] ?? ($headers['cache-control'][0] ?? '');

            return [
                'load_time_ms'  => $loadTime,
                'page_size'     => $size,
                'page_size_kb'  => round($size / 1024, 1),
                'status_code'   => $response->status(),
                'has_gzip'      => $hasGzip,
                'cache_control' => $cacheControl,
                'server'        => $headers['Server'][0] ?? ($headers['server'][0] ?? 'Unknown'),
            ];
        } catch (\Exception $e) {
            return [
                'load_time_ms' => 0,
                'page_size'    => 0,
                'page_size_kb' => 0,
                'status_code'  => 0,
                'has_gzip'     => false,
                'error'        => $e->getMessage(),
            ];
        }
    }

    // ================================================================
    // Sitemap Check
    // ================================================================

    private function checkSitemap(string $url): array
    {
        $sitemapUrl = rtrim($url, '/') . '/sitemap.xml';
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MyCommunicationERP/1.0)'])
                ->get($sitemapUrl);

            $exists = $response->successful() && stripos($response->body(), '<urlset') !== false;
            $urlCount = 0;
            if ($exists) {
                $urlCount = substr_count($response->body(), '<url>');
                if ($urlCount === 0) {
                    $urlCount = substr_count($response->body(), '<sitemap>');
                }
            }

            return [
                'url'       => $sitemapUrl,
                'exists'    => $exists,
                'status'    => $response->status(),
                'url_count' => $urlCount,
            ];
        } catch (\Exception $e) {
            return ['url' => $sitemapUrl, 'exists' => false, 'status' => 0, 'error' => $e->getMessage()];
        }
    }

    // ================================================================
    // Robots.txt Check
    // ================================================================

    private function checkRobots(string $url): array
    {
        $robotsUrl = rtrim($url, '/') . '/robots.txt';
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MyCommunicationERP/1.0)'])
                ->get($robotsUrl);

            $body = $response->body();
            $exists = $response->successful() && stripos($body, 'user-agent') !== false;

            $hasSitemap = $exists && stripos($body, 'sitemap') !== false;
            $hasDisallow = $exists && stripos($body, 'disallow') !== false;

            return [
                'url'          => $robotsUrl,
                'exists'       => $exists,
                'status'       => $response->status(),
                'content'      => $exists ? mb_substr($body, 0, 2000) : null,
                'has_sitemap'  => $hasSitemap,
                'has_disallow' => $hasDisallow,
            ];
        } catch (\Exception $e) {
            return ['url' => $robotsUrl, 'exists' => false, 'status' => 0, 'error' => $e->getMessage()];
        }
    }

    // ================================================================
    // SSL Check
    // ================================================================

    private function checkSsl(string $url): array
    {
        $isHttps = str_starts_with($url, 'https://');
        $host = parse_url($url, PHP_URL_HOST);

        $result = ['https' => $isHttps, 'host' => $host];

        if ($isHttps && $host) {
            try {
                $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
                $client = @stream_socket_client(
                    'ssl://' . $host . ':443', $errno, $errstr, 10,
                    STREAM_CLIENT_CONNECT, $context
                );
                if ($client) {
                    $params = stream_context_get_params($client);
                    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? '');
                    if ($cert) {
                        $result['issuer']    = $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? 'Unknown');
                        $result['valid_from'] = date('Y-m-d', $cert['validFrom_time_t'] ?? 0);
                        $result['valid_to']   = date('Y-m-d', $cert['validTo_time_t'] ?? 0);
                        $result['days_left']  = max(0, (int) ceil(($cert['validTo_time_t'] - time()) / 86400));
                        $result['valid']      = $result['days_left'] > 0;
                    }
                    fclose($client);
                }
            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
            }
        }

        return $result;
    }

    // ================================================================
    // Score Calculator
    // ================================================================

    private function calculateScore(array $meta, array $headings, array $images, array $links, array $schema, array $sitemap, array $robots, array $ssl): int
    {
        $score = 0;
        $max   = 0;

        // Meta (30 points)
        $max += 30;
        if ($meta['has_title']) $score += 5;
        if ($meta['title_length'] >= 30 && $meta['title_length'] <= 65) $score += 3;
        if ($meta['has_description']) $score += 5;
        if ($meta['desc_length'] >= 120 && $meta['desc_length'] <= 160) $score += 3;
        if ($meta['has_viewport']) $score += 4;
        if ($meta['has_og']) $score += 4;
        if ($meta['has_twitter']) $score += 3;
        if ($meta['has_canonical']) $score += 3;

        // Headings (15 points)
        $max += 15;
        if ($headings['has_h1']) $score += 8;
        if ($headings['h1_count'] === 1) $score += 4;
        if ($headings['total'] >= 3) $score += 3;

        // Images (15 points)
        $max += 15;
        if ($images['total'] > 0) {
            $altRatio = ($images['total'] - $images['without_alt']) / $images['total'];
            $score += (int) round($altRatio * 10);
            if ($images['lazy_loaded'] > 0) $score += 5;
        } else {
            $score += 15;
        }

        // Links (10 points)
        $max += 10;
        if ($links['internal'] > 0) $score += 5;
        if ($links['external'] > 0) $score += 3;
        if ($links['empty'] === 0) $score += 2;

        // Schema (10 points)
        $max += 10;
        if ($schema['has_schema']) $score += 10;

        // Sitemap (8 points)
        $max += 8;
        if ($sitemap['exists']) $score += 8;

        // Robots (7 points)
        $max += 7;
        if ($robots['exists']) $score += 4;
        if ($robots['has_sitemap'] ?? false) $score += 3;

        // SSL (5 points)
        $max += 5;
        if ($ssl['https']) $score += 3;
        if ($ssl['valid'] ?? false) $score += 2;

        return $max > 0 ? (int) round(($score / $max) * 100) : 0;
    }

    // ================================================================
    // Issues Collector
    // ================================================================

    private function collectIssues(array $meta, array $headings, array $images, array $links, array $schema, array $sitemap, array $robots, array $ssl): array
    {
        $issues = [];

        // Critical
        if (!$meta['has_title']) $issues[] = ['level' => 'critical', 'msg' => 'Missing page title'];
        if (!$meta['has_description']) $issues[] = ['level' => 'critical', 'msg' => 'Missing meta description'];
        if (!$headings['has_h1']) $issues[] = ['level' => 'critical', 'msg' => 'Missing H1 heading'];
        if (!($ssl['https'] ?? false)) $issues[] = ['level' => 'critical', 'msg' => 'Site not using HTTPS'];

        // Warning
        if ($meta['title_length'] > 65) $issues[] = ['level' => 'warning', 'msg' => 'Title too long (' . $meta['title_length'] . ' chars, recommended max 65)'];
        if ($meta['title_length'] > 0 && $meta['title_length'] < 30) $issues[] = ['level' => 'warning', 'msg' => 'Title too short (' . $meta['title_length'] . ' chars, recommended min 30)'];
        if ($meta['desc_length'] > 160) $issues[] = ['level' => 'warning', 'msg' => 'Description too long (' . $meta['desc_length'] . ' chars, recommended max 160)'];
        if ($meta['desc_length'] > 0 && $meta['desc_length'] < 120) $issues[] = ['level' => 'warning', 'msg' => 'Description too short (' . $meta['desc_length'] . ' chars, recommended min 120)'];
        if ($headings['h1_count'] > 1) $issues[] = ['level' => 'warning', 'msg' => 'Multiple H1 tags found (' . $headings['h1_count'] . ')'];
        if ($images['without_alt'] > 0) $issues[] = ['level' => 'warning', 'msg' => $images['without_alt'] . ' images missing alt text'];
        if (!$meta['has_viewport']) $issues[] = ['level' => 'warning', 'msg' => 'Missing viewport meta tag (mobile-friendliness)'];

        // Info
        if (!$meta['has_og']) $issues[] = ['level' => 'info', 'msg' => 'Missing Open Graph tags'];
        if (!$meta['has_twitter']) $issues[] = ['level' => 'info', 'msg' => 'Missing Twitter Card tags'];
        if (!$meta['has_canonical']) $issues[] = ['level' => 'info', 'msg' => 'Missing canonical URL'];
        if (!$schema['has_schema']) $issues[] = ['level' => 'info', 'msg' => 'No structured data (Schema.org) found'];
        if (!$sitemap['exists']) $issues[] = ['level' => 'info', 'msg' => 'Sitemap.xml not found or invalid'];
        if (!$robots['exists']) $issues[] = ['level' => 'info', 'msg' => 'Robots.txt not found'];
        if ($links['empty'] > 0) $issues[] = ['level' => 'info', 'msg' => $links['empty'] . ' empty/anchor-only links found'];
        if ($images['total'] > 0 && $images['lazy_loaded'] === 0) $issues[] = ['level' => 'info', 'msg' => 'No lazy-loaded images detected'];
        if (isset($ssl['days_left']) && $ssl['days_left'] < 30) $issues[] = ['level' => 'warning', 'msg' => 'SSL certificate expires in ' . $ssl['days_left'] . ' days'];

        return $issues;
    }

    // ================================================================
    // HTML Helpers
    // ================================================================

    private function extractTag(string $html, string $open, string $close): ?string
    {
        $start = stripos($html, $open);
        if ($start === false) return null;
        $start += strlen($open);
        $end = stripos($html, $close, $start);
        if ($end === false) return null;
        return trim(strip_tags(substr($html, $start, $end - $start)));
    }

    private function extractMeta(string $html, string $name, string $attr = 'name'): ?string
    {
        $pattern = '/<meta\s[^>]*' . preg_quote($attr) . '=["\']' . preg_quote($name) . '["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/si';
        if (preg_match($pattern, $html, $m)) return $m[1];

        $pattern2 = '/<meta\s[^>]*content=["\']([^"\']*)["\'][^>]*' . preg_quote($attr) . '=["\']' . preg_quote($name) . '["\'][^>]*>/si';
        if (preg_match($pattern2, $html, $m)) return $m[1];

        return null;
    }

    private function extractLink(string $html, string $rel): ?string
    {
        $pattern = '/<link\s[^>]*rel=["\']' . preg_quote($rel) . '["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/si';
        if (preg_match($pattern, $html, $m)) return $m[1];

        $pattern2 = '/<link\s[^>]*href=["\']([^"\']*)["\'][^>]*rel=["\']' . preg_quote($rel) . '["\'][^>]*>/si';
        if (preg_match($pattern2, $html, $m)) return $m[1];

        return null;
    }

    private function extractCharset(string $html): ?string
    {
        if (preg_match('/<meta\s[^>]*charset=["\']?([^"\'\s>]+)/si', $html, $m)) return $m[1];
        return null;
    }

    private function extractLang(string $html): ?string
    {
        if (preg_match('/<html[^>]*\slang=["\']([^"\']+)["\']/', $html, $m)) return $m[1];
        return null;
    }

    private function extractAttr(string $tag, string $attr): ?string
    {
        if (preg_match('/\s' . preg_quote($attr) . '=["\']([^"\']*)["\']/', $tag, $m)) return $m[1];
        return null;
    }
}
