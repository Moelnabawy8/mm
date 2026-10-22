<?php

namespace Modules\VideoStreaming\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AntiDownloadMiddleware
{
    /**
     * Known download manager user agents.
     */
    protected array $blockedAgents = [
        'idm',
        'internet download manager',
        'flashget',
        'getright',
        'download',
        'accelerator',
        'wget',
        'curl',
        'httrack',
        'offline',
        'grab',
        'ninja',
        'webcopier',
        'teleport',
        'webcapture',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        // Check for download managers
        foreach ($this->blockedAgents as $agent) {
            if (str_contains($userAgent, $agent)) {
                Log::warning('Download manager blocked', [
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);
                
                abort(403, 'Download managers are not allowed');
            }
        }

        // Check for suspicious request patterns
        if ($this->isSuspiciousRequest($request)) {
            Log::warning('Suspicious video request blocked', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'headers' => $request->headers->all(),
            ]);
            
            abort(403, 'Access denied');
        }

        $response = $next($request);

        // Add security headers to prevent download
        if ($response instanceof Response) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Disposition', 'inline');
            
            // Prevent caching of sensitive responses
            if ($request->is('*/key/*') || $request->is('*/playlist/*')) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
            }
        }

        return $response;
    }

    /**
     * Check for suspicious request patterns.
     */
    protected function isSuspiciousRequest(Request $request): bool
    {
        // Check for range requests that indicate download attempts
        $range = $request->header('Range');
        
        if ($range) {
            // Allow legitimate range requests for video playback
            // But block requests for entire file
            if (preg_match('/bytes=0-$/i', $range)) {
                // Request for entire file - suspicious for streaming
                // However, this might be legitimate for some players
                // Log but don't block
                Log::info('Full file range request', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);
            }
        }

        // Check for missing expected headers
        $acceptHeader = $request->header('Accept');
        $secFetchDest = $request->header('Sec-Fetch-Dest');
        
        // Modern browsers send these headers
        // Downloaders often don't
        if (!$acceptHeader && !$secFetchDest) {
            // Missing both headers is suspicious
            // But could be from older browsers
            return false; // Don't block, just note
        }

        return false;
    }
}
