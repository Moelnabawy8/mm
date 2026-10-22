<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ApiLog Model (Black Box)
 * 
 * Records every API request for comprehensive auditing and debugging.
 * Stores request details, response data, and error information.
 * 
 * @property int $id
 * @property int|null $api_client_id
 * @property string $method
 * @property string $endpoint
 * @property string|null $full_url
 * @property array|null $headers
 * @property array|null $payload
 * @property array|null $query_params
 * @property int $response_code
 * @property array|null $response_body
 * @property float|null $response_time
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $error_message
 * @property string|null $exception_trace
 * @property string|null $ability_used
 * @property bool $ability_granted
 * @property \Carbon\Carbon $created_at
 */
class ApiLog extends Model
{
    /**
     * Disable updated_at since logs are immutable.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'api_client_id',
        'method',
        'endpoint',
        'full_url',
        'headers',
        'payload',
        'query_params',
        'response_code',
        'response_body',
        'response_time',
        'ip_address',
        'user_agent',
        'error_message',
        'exception_trace',
        'ability_used',
        'ability_granted',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'query_params' => 'array',
        'response_body' => 'array',
        'response_code' => 'integer',
        'response_time' => 'float',
        'ability_granted' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Headers that should be excluded from logging (sensitive data).
     */
    public static array $excludedHeaders = [
        'authorization',
        'cookie',
        'x-api-key',
        'x-auth-token',
        'php-auth-pw',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the API client that made this request.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    // ==================== FACTORY METHOD ====================

    /**
     * Create a new log entry from a request.
     */
    public static function logRequest(
        ?ApiClient $client,
        \Illuminate\Http\Request $request,
        int $responseCode,
        mixed $responseBody = null,
        ?float $responseTime = null,
        ?string $errorMessage = null,
        ?string $exceptionTrace = null,
        ?string $abilityUsed = null,
        bool $abilityGranted = true
    ): self {
        // Sanitize headers (remove sensitive data)
        $headers = collect($request->headers->all())
            ->except(self::$excludedHeaders)
            ->map(fn($values) => implode(', ', $values))
            ->toArray();

        // Truncate large payloads
        $payload = $request->all();
        $payloadJson = json_encode($payload);
        if (strlen($payloadJson) > 65000) {
            $payload = ['_truncated' => true, '_size' => strlen($payloadJson)];
        }

        // Truncate large responses
        $responseBodyTruncated = $responseBody;
        if (is_array($responseBody) || is_object($responseBody)) {
            $responseJson = json_encode($responseBody);
            if (strlen($responseJson) > 65000) {
                $responseBodyTruncated = ['_truncated' => true, '_size' => strlen($responseJson)];
            }
        }

        return static::create([
            'api_client_id' => $client?->id,
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'full_url' => $request->fullUrl(),
            'headers' => $headers,
            'payload' => $payload,
            'query_params' => $request->query(),
            'response_code' => $responseCode,
            'response_body' => $responseBodyTruncated,
            'response_time' => $responseTime,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'error_message' => $errorMessage,
            'exception_trace' => $exceptionTrace,
            'ability_used' => $abilityUsed,
            'ability_granted' => $abilityGranted,
        ]);
    }

    // ==================== STATUS HELPERS ====================

    /**
     * Check if this was a successful request.
     */
    public function isSuccess(): bool
    {
        return $this->response_code >= 200 && $this->response_code < 300;
    }

    /**
     * Check if this was a client error (4xx).
     */
    public function isClientError(): bool
    {
        return $this->response_code >= 400 && $this->response_code < 500;
    }

    /**
     * Check if this was a server error (5xx).
     */
    public function isServerError(): bool
    {
        return $this->response_code >= 500;
    }

    /**
     * Check if this was any kind of error.
     */
    public function isError(): bool
    {
        return $this->response_code >= 400;
    }

    /**
     * Get the status category for UI display.
     */
    public function getStatusCategoryAttribute(): string
    {
        if ($this->isSuccess()) return 'success';
        if ($this->isClientError()) return 'warning';
        if ($this->isServerError()) return 'danger';
        return 'info';
    }

    /**
     * Get CSS class for status badge.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status_category) {
            'success' => 'bg-success',
            'warning' => 'bg-warning',
            'danger' => 'bg-danger',
            default => 'bg-info',
        };
    }

    /**
     * Get color for row highlighting.
     */
    public function getRowColorAttribute(): string
    {
        return match ($this->status_category) {
            'success' => 'table-success',
            'warning' => 'table-warning',
            'danger' => 'table-danger',
            default => '',
        };
    }

    // ==================== SCOPES ====================

    /**
     * Scope: Only successful requests.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('response_code', '>=', 200)
                     ->where('response_code', '<', 300);
    }

    /**
     * Scope: Only failed requests.
     */
    public function scopeFailed($query)
    {
        return $query->where('response_code', '>=', 400);
    }

    /**
     * Scope: Only client errors (4xx).
     */
    public function scopeClientErrors($query)
    {
        return $query->where('response_code', '>=', 400)
                     ->where('response_code', '<', 500);
    }

    /**
     * Scope: Only server errors (5xx).
     */
    public function scopeServerErrors($query)
    {
        return $query->where('response_code', '>=', 500);
    }

    /**
     * Scope: Today's logs only.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope: This week's logs.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope: Filter by client.
     */
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('api_client_id', $clientId);
    }

    /**
     * Scope: Filter by HTTP method.
     */
    public function scopeMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    // ==================== STATISTICS ====================

    /**
     * Get aggregate statistics for a given period.
     */
    public static function getStats(?int $clientId = null, string $period = 'today'): array
    {
        $query = static::query();

        if ($clientId) {
            $query->forClient($clientId);
        }

        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        $total = $query->count();
        $successful = (clone $query)->successful()->count();
        $failed = (clone $query)->failed()->count();
        $avgResponseTime = (clone $query)->avg('response_time');

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 100,
            'avg_response_time' => round($avgResponseTime ?? 0, 2),
        ];
    }

    /**
     * Get hourly request counts for charts.
     */
    public static function getHourlyStats(?int $clientId = null): array
    {
        $query = static::today();

        if ($clientId) {
            $query->forClient($clientId);
        }

        return $query->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
    }
}
