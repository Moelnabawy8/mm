<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventHotlinking
{
    public function handle(Request $request, Closure $next): Response
    {
        $referer = $request->header('Referer');

        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);

            $allowedHosts = ['localhost', '127.0.0.1', $appHost];

            // Include all allowed domains (both global and per-video)
            $allowedDomains = \App\Models\AllowedDomain::pluck('domain')->toArray();
            $allowedHosts = array_merge($allowedHosts, $allowedDomains);

            if (!in_array($refererHost, $allowedHosts)) {
                abort(403, 'Hotlinking not allowed');
            }
        }

        return $next($request);
    }
}
