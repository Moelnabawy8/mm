<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LogApiActivity Middleware
 * 
 * Intercepts all API requests from external clients (n8n).
 * Responsibilities:
 * 1. Authenticate the API client via Bearer token
 * 2. Validate client status (active, not expired)
 * 3. Check IP whitelist restrictions
 * 4. Log all request/response details to api_logs
 * 
 * Usage in routes:
 *   Route::middleware('log.api.activity')->group(function() { ... });
 */
class LogApiActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $client = null;
        $errorMessage = null;
        $abilityUsed = null;
        $abilityGranted = true;

        try {
            // Extract Bearer token from Authorization header
            $token = $this->extractBearerToken($request);

            if (!$token) {
                return $this->respondUnauthorized(
                    $request, 
                    $startTime, 
                    null, 
                    'Missing API token. Provide Authorization: Bearer <token>'
                );
            }

            // Find the client by token
            $client = ApiClient::findByToken($token);

            if (!$client) {
                return $this->respondUnauthorized(
                    $request, 
                    $startTime, 
                    null, 
                    'Invalid API token'
                );
            }

            // Check if client is active
            if (!$client->is_active) {
                return $this->respondForbidden(
                    $request, 
                    $startTime, 
                    $client, 
                    'API client is deactivated'
                );
            }

            // Check if token has expired
            if ($client->isExpired()) {
                return $this->respondForbidden(
                    $request, 
                    $startTime, 
                    $client, 
                    'API token has expired'
                );
            }

            // Check IP whitelist
            if (!$client->isIpAllowed($request->ip())) {
                return $this->respondForbidden(
                    $request, 
                    $startTime, 
                    $client, 
                    'IP address not allowed: ' . $request->ip()
                );
            }

            // Store client in request for later use
            $request->attributes->set('api_client', $client);

            // Record usage
            $client->recordUsage();

            // Proceed with the request
            $response = $next($request);

            // Log successful request
            $this->logRequest(
                $client,
                $request,
                $response->getStatusCode(),
                $this->getResponseContent($response),
                $this->calculateResponseTime($startTime),
                null,
                null,
                $request->attributes->get('ability_used'),
                $request->attributes->get('ability_granted', true)
            );

            return $response;

        } catch (\Exception $e) {
            // Log the error and return error response
            $this->logRequest(
                $client,
                $request,
                500,
                ['error' => $e->getMessage()],
                $this->calculateResponseTime($startTime),
                $e->getMessage(),
                config('app.debug') ? $e->getTraceAsString() : null,
                $abilityUsed,
                $abilityGranted
            );

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Extract Bearer token from request.
     */
    protected function extractBearerToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            // Try X-API-Key header as fallback
            return $request->header('X-API-Key');
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return null;
    }

    /**
     * Calculate response time in milliseconds.
     */
    protected function calculateResponseTime(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 3);
    }

    /**
     * Get response content for logging.
     */
    protected function getResponseContent(Response $response): ?array
    {
        $content = $response->getContent();
        
        if (!$content) {
            return null;
        }

        $decoded = json_decode($content, true);
        
        return $decoded ?? ['_raw' => substr($content, 0, 1000)];
    }

    /**
     * Log the API request.
     */
    protected function logRequest(
        ?ApiClient $client,
        Request $request,
        int $responseCode,
        ?array $responseBody,
        float $responseTime,
        ?string $errorMessage,
        ?string $exceptionTrace,
        ?string $abilityUsed,
        bool $abilityGranted
    ): void {
        ApiLog::logRequest(
            $client,
            $request,
            $responseCode,
            $responseBody,
            $responseTime,
            $errorMessage,
            $exceptionTrace,
            $abilityUsed,
            $abilityGranted
        );
    }

    /**
     * Return 401 Unauthorized response.
     */
    protected function respondUnauthorized(
        Request $request, 
        float $startTime, 
        ?ApiClient $client, 
        string $message
    ): Response {
        $this->logRequest(
            $client,
            $request,
            401,
            ['error' => $message],
            $this->calculateResponseTime($startTime),
            $message,
            null,
            null,
            false
        );

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'UNAUTHORIZED',
        ], 401);
    }

    /**
     * Return 403 Forbidden response.
     */
    protected function respondForbidden(
        Request $request, 
        float $startTime, 
        ?ApiClient $client, 
        string $message
    ): Response {
        $this->logRequest(
            $client,
            $request,
            403,
            ['error' => $message],
            $this->calculateResponseTime($startTime),
            $message,
            null,
            null,
            false
        );

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'FORBIDDEN',
        ], 403);
    }
}
