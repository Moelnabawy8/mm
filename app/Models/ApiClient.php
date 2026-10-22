<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// SoftDeletes removed - not using deleted_at column
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ApiClient Model
 * 
 * Represents an external API client (like n8n) that can interact with the ERP.
 * Handles token generation, ability checking, and IP validation.
 * 
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $token_hash
 * @property string $token_prefix
 * @property array $abilities
 * @property array|null $allowed_ips
 * @property int $rate_limit
 * @property bool $is_active
 * @property \Carbon\Carbon|null $last_used_at
 * @property int $total_requests
 * @property int $created_by
 * @property \Carbon\Carbon|null $expires_at
 */
class ApiClient extends Model
{
    // SoftDeletes trait removed

    protected $fillable = [
        'name',
        'description',
        'token_hash',
        'token_prefix',
        'abilities',
        'allowed_ips',
        'rate_limit',
        'is_active',
        'last_used_at',
        'total_requests',
        'created_by',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'rate_limit' => 'integer',
        'total_requests' => 'integer',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Available abilities/scopes for API clients.
     * Format: 'scope' => 'Human readable description'
     */
    public static array $availableAbilities = [
        // User Management
        'users:read' => 'View users list and details',
        'users:create' => 'Create new users',
        'users:update' => 'Update existing users',
        'users:delete' => 'Delete users',
        
        // Customer Management
        'customers:read' => 'View customers list and details',
        'customers:create' => 'Create new customers',
        'customers:update' => 'Update existing customers',
        'customers:delete' => 'Delete customers',
        
        // Invoice Management
        'invoices:read' => 'View invoices list and details',
        'invoices:create' => 'Create new invoices',
        'invoices:update' => 'Update existing invoices',
        'invoices:delete' => 'Delete invoices',
        
        // Bill Management
        'bills:read' => 'View bills list and details',
        'bills:create' => 'Create new bills',
        'bills:update' => 'Update existing bills',
        'bills:delete' => 'Delete bills',
        
        // Product & Inventory
        'products:read' => 'View products and inventory',
        'products:create' => 'Create new products',
        'products:update' => 'Update products and stock',
        'products:delete' => 'Delete products',
        'inventory:update' => 'Update inventory levels',
        
        // Employee/HRM
        'employees:read' => 'View employees list and details',
        'employees:create' => 'Create new employees',
        'employees:update' => 'Update employee records',
        'employees:delete' => 'Delete employees',
        'attendance:read' => 'View attendance records',
        'attendance:create' => 'Create attendance entries',
        'payroll:read' => 'View payroll data',
        'payroll:create' => 'Process payroll',
        
        // Projects
        'projects:read' => 'View projects list and details',
        'projects:create' => 'Create new projects',
        'projects:update' => 'Update existing projects',
        'projects:delete' => 'Delete projects',
        'tasks:read' => 'View project tasks',
        'tasks:create' => 'Create new tasks',
        'tasks:update' => 'Update tasks',
        'tasks:delete' => 'Delete tasks',
        
        // CRM
        'leads:read' => 'View leads list and details',
        'leads:create' => 'Create new leads',
        'leads:update' => 'Update existing leads',
        'leads:delete' => 'Delete leads',
        'deals:read' => 'View deals list and details',
        'deals:create' => 'Create new deals',
        'deals:update' => 'Update existing deals',
        'deals:delete' => 'Delete deals',
        
        // Reports
        'reports:read' => 'Access and generate reports',
        
        // Settings (Admin)
        'settings:read' => 'View system settings',
        'settings:update' => 'Modify system settings',
        
        // Full Access
        '*' => 'Full unrestricted access (Super Admin)',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who created this API client.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all API logs for this client.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class, 'api_client_id');
    }

    // ==================== TOKEN MANAGEMENT ====================

    /**
     * Generate a new API token for this client.
     * Returns the plain text token (only shown once).
     */
    public static function generateToken(array $attributes): array
    {
        // Generate a secure random token
        $plainToken = 'n8n_' . Str::random(48);
        $prefix = substr($plainToken, 0, 8);
        
        // Create the client with hashed token
        $client = static::create([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'token_hash' => hash('sha256', $plainToken),
            'token_prefix' => $prefix,
            'abilities' => $attributes['abilities'] ?? [],
            'allowed_ips' => $attributes['allowed_ips'] ?? null,
            'rate_limit' => $attributes['rate_limit'] ?? 60,
            'is_active' => true,
            'created_by' => $attributes['created_by'],
            'expires_at' => $attributes['expires_at'] ?? null,
        ]);

        return [
            'client' => $client,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Find a client by their plain text token.
     */
    public static function findByToken(string $token): ?self
    {
        $hash = hash('sha256', $token);
        return static::where('token_hash', $hash)->first();
    }

    /**
     * Verify if a plain text token matches this client's hash.
     */
    public function verifyToken(string $token): bool
    {
        return hash_equals($this->token_hash, hash('sha256', $token));
    }

    // ==================== ABILITY/SCOPE CHECKING ====================

    /**
     * Check if the client has a specific ability.
     */
    public function hasAbility(string $ability): bool
    {
        // Super admin wildcard
        if (in_array('*', $this->abilities ?? [])) {
            return true;
        }

        // Direct match
        if (in_array($ability, $this->abilities ?? [])) {
            return true;
        }

        // Wildcard within category (e.g., "users:*")
        $category = explode(':', $ability)[0] ?? '';
        if (in_array("{$category}:*", $this->abilities ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Check if client has any of the given abilities.
     */
    public function hasAnyAbility(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->hasAbility($ability)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if client has all of the given abilities.
     */
    public function hasAllAbilities(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if (!$this->hasAbility($ability)) {
                return false;
            }
        }
        return true;
    }

    // ==================== IP VALIDATION ====================

    /**
     * Check if an IP address is allowed for this client.
     */
    public function isIpAllowed(string $ip): bool
    {
        // If no IPs specified, allow all
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    // ==================== STATUS CHECKING ====================

    /**
     * Check if the client is currently valid and can make requests.
     */
    public function isValid(): bool
    {
        // Must be active
        if (!$this->is_active) {
            return false;
        }

        // Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // ==================== USAGE TRACKING ====================

    /**
     * Record that the client made a request.
     */
    public function recordUsage(): void
    {
        $this->increment('total_requests');
        $this->update(['last_used_at' => now()]);
    }

    // ==================== SCOPES ====================

    /**
     * Scope: Only active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only non-expired clients.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: Valid clients (active and not expired).
     */
    public function scopeValid($query)
    {
        return $query->active()->notExpired();
    }

    // ==================== STATISTICS ====================

    /**
     * Get request count for today.
     */
    public function getTodayRequestsCountAttribute(): int
    {
        return $this->logs()
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Get failed request count for today.
     */
    public function getTodayFailedRequestsCountAttribute(): int
    {
        return $this->logs()
            ->whereDate('created_at', today())
            ->where('response_code', '>=', 400)
            ->count();
    }

    /**
     * Get success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->total_requests;
        if ($total === 0) {
            return 100.0;
        }

        $failed = $this->logs()->where('response_code', '>=', 400)->count();
        return round((($total - $failed) / $total) * 100, 2);
    }
}
