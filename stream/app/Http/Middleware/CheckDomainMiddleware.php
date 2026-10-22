<?php

namespace App\Http\Middleware;

use App\Services\StreamService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDomainMiddleware
{
    public function __construct(protected StreamService $streamService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->streamService->verifyDomain($request)) {
            abort(403, 'Domain not allowed');
        }

        return $next($request);
    }
}
