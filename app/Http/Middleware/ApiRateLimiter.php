<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiRateLimiter Middleware
 * 
 * Implements per-client rate limiting based on the client's configured rate_limit.
 * Uses Laravel Cache for tracking request counts.
 * 
 * Rate limit is per-minute and respects each client's individual limit setting.
 */
class ApiRateLimiter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->attributes->get('api_client');

        if (!$client instanceof ApiClient) {
            return $next($request);
        }

        $key = 'api_rate_limit:' . $client->id;
        $maxAttempts = $client->rate_limit;
        $decayMinutes = 1;

        // Get current attempt count
        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            // Calculate retry-after time
            $retryAfter = Cache::get($key . ':timer', 60);

            // Log the rate limit hit
            ApiLog::logRequest(
                $client,
                $request,
                429,
                ['error' => 'Rate limit exceeded'],
                0,
                'Rate limit exceeded: ' . $attempts . '/' . $maxAttempts,
                null,
                null,
                false
            );

            return response()->json([
                'success' => false,
                'message' => 'Too many requests',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'remaining' => 0,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ]);
        }

        // Increment attempts
        if ($attempts === 0) {
            Cache::put($key, 1, now()->addMinutes($decayMinutes));
            Cache::put($key . ':timer', 60, now()->addMinutes($decayMinutes));
        } else {
            Cache::increment($key);
        }

        $response = $next($request);

        // Add rate limit headers to response
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $maxAttempts - $attempts - 1),
        ]);
    }
}
