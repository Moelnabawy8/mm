<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckApiAbility Middleware
 * 
 * Verifies that the authenticated API client has the required ability (scope)
 * to access the requested endpoint.
 * 
 * Usage in routes:
 *   Route::middleware('api.ability:users:read')->get('/users', ...);
 *   Route::middleware('api.ability:users:create,users:update')->post('/users', ...);
 * 
 * Multiple abilities can be specified (comma-separated) - client needs ANY of them.
 */
class CheckApiAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$abilities  Required abilities (OR logic)
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        // Get the API client from request (set by LogApiActivity middleware)
        $client = $request->attributes->get('api_client');

        if (!$client instanceof ApiClient) {
            return response()->json([
                'success' => false,
                'message' => 'API client not authenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Store ability check info for logging
        $request->attributes->set('ability_used', implode(',', $abilities));

        // Check if client has any of the required abilities
        if (!$client->hasAnyAbility($abilities)) {
            $request->attributes->set('ability_granted', false);
            
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
                'code' => 'FORBIDDEN',
                'required_abilities' => $abilities,
                'your_abilities' => $client->abilities,
            ], 403);
        }

        $request->attributes->set('ability_granted', true);

        return $next($request);
    }
}
