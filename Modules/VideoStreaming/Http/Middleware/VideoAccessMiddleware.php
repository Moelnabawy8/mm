<?php

namespace Modules\VideoStreaming\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VideoAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for valid token
        $token = $request->query('token') ?? $request->header('X-Video-Token');
        
        if (!$token) {
            Log::warning('Video access attempt without token', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
            ]);
            
            abort(401, 'Access token required');
        }

        // Anti-hotlinking: Check referer
        $referer = $request->header('Referer');
        $origin = $request->header('Origin');
        
        if (config('videostreaming.security.enable_domain_lock')) {
            $allowedDomains = explode(',', config('videostreaming.security.allowed_domains', ''));
            $appDomain = parse_url(config('app.url'), PHP_URL_HOST);
            $allowedDomains[] = $appDomain;
            $allowedDomains = array_filter(array_map('trim', $allowedDomains));

            $requestDomain = null;
            
            if ($referer) {
                $requestDomain = parse_url($referer, PHP_URL_HOST);
            } elseif ($origin) {
                $requestDomain = parse_url($origin, PHP_URL_HOST);
            }

            if ($requestDomain && !empty($allowedDomains)) {
                $isAllowed = false;
                foreach ($allowedDomains as $domain) {
                    if ($domain && (str_ends_with($requestDomain, $domain) || $requestDomain === $domain)) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    Log::warning('Video hotlinking attempt blocked', [
                        'domain' => $requestDomain,
                        'ip' => $request->ip(),
                        'path' => $request->path(),
                    ]);
                    
                    abort(403, 'Access denied - domain not allowed');
                }
            }
        }

        return $next($request);
    }
}
