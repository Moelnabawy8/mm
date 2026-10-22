<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: API Clients Table
 * 
 * Stores API client credentials for external integrations like n8n.
 * Each client has a hashed token, defined abilities (scopes), and optional IP restrictions.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            
            // Client identification
            $table->string('name')->comment('Descriptive name for the API client (e.g., "n8n Production")');
            $table->string('description')->nullable()->comment('Optional description of client purpose');
            
            // Authentication
            $table->string('token_hash', 64)->unique()->comment('SHA-256 hash of the API token');
            $table->string('token_prefix', 8)->comment('First 8 chars of token for identification');
            
            // Authorization - JSON array of abilities/scopes
            $table->json('abilities')->comment('JSON array of scopes like ["users:read", "orders:create"]');
            
            // IP Whitelisting - JSON array of allowed IPs
            $table->json('allowed_ips')->nullable()->comment('JSON array of whitelisted IPs, null = allow all');
            
            // Rate limiting
            $table->integer('rate_limit')->default(60)->comment('Max requests per minute');
            
            // Status & Tracking
            $table->boolean('is_active')->default(true)->comment('Whether this client is currently active');
            $table->timestamp('last_used_at')->nullable()->comment('Last API request timestamp');
            $table->unsignedBigInteger('total_requests')->default(0)->comment('Total API requests made');
            
            // Ownership
            $table->unsignedBigInteger('created_by')->comment('Admin user who created this client');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            // Expiration
            $table->timestamp('expires_at')->nullable()->comment('Token expiration date, null = never expires');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('is_active');
            $table->index('last_used_at');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
