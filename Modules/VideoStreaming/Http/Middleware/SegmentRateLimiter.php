<?php

namespace Modules\VideoStreaming\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SegmentRateLimiter
{
    protected int $maxRequestsPerMinute = 120;
    protected int $maxConcurrentRequests = 10;

    public function handle(Request $request, Closure $next)
    {
        $identifier = $this->getIdentifier($request);

        if ($this->isRateLimited($identifier)) {
            Log::warning("Rate limit exceeded for: {$identifier}");
            return response()->json(['error' => 'Too many requests'], 429);
        }

        if ($this->hasTooManyConcurrentRequests($identifier)) {
            Log::warning("Concurrent request limit exceeded for: {$identifier}");
            return response()->json(['error' => 'Too many concurrent requests'], 429);
        }

        $this->incrementConcurrentRequests($identifier);

        $response = $next($request);

        $this->decrementConcurrentRequests($identifier);
        $this->recordRequest($identifier);

        return $response;
    }

    protected function getIdentifier(Request $request): string
    {
        $token = $request->query('token', 'anonymous');
        $ip = $request->ip();
        return "segment_rate:{$ip}:{$token}";
    }

    protected function isRateLimited(string $identifier): bool
    {
        $key = "{$identifier}:count";
        $count = Cache::get($key, 0);

        return $count >= $this->maxRequestsPerMinute;
    }

    protected function recordRequest(string $identifier): void
    {
        $key = "{$identifier}:count";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, 60);
    }

    protected function hasTooManyConcurrentRequests(string $identifier): bool
    {
        $key = "{$identifier}:concurrent";
        $concurrent = Cache::get($key, 0);

        return $concurrent >= $this->maxConcurrentRequests;
    }

    protected function incrementConcurrentRequests(string $identifier): void
    {
        $key = "{$identifier}:concurrent";
        $concurrent = Cache::get($key, 0);
        Cache::put($key, $concurrent + 1, 300);
    }

    protected function decrementConcurrentRequests(string $identifier): void
    {
        $key = "{$identifier}:concurrent";
        $concurrent = Cache::get($key, 0);

        if ($concurrent > 0) {
            Cache::put($key, $concurrent - 1, 300);
        }
    }
}
