<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: API Logs Table (Black Box)
 * 
 * Records every API request for auditing, debugging, and monitoring.
 * Acts as a comprehensive audit trail for all external API interactions.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            
            // Client reference (nullable for unauthenticated requests)
            $table->unsignedBigInteger('api_client_id')->nullable();
            $table->foreign('api_client_id')->references('id')->on('api_clients')->onDelete('set null');
            
            // Request details
            $table->string('method', 10)->comment('HTTP method: GET, POST, PUT, PATCH, DELETE');
            $table->string('endpoint', 500)->comment('Full endpoint path requested');
            $table->string('full_url', 1000)->nullable()->comment('Complete URL with query parameters');
            
            // Request data
            $table->json('headers')->nullable()->comment('Request headers (sanitized)');
            $table->json('payload')->nullable()->comment('Request body/payload');
            $table->json('query_params')->nullable()->comment('URL query parameters');
            
            // Response data
            $table->integer('response_code')->comment('HTTP response status code');
            $table->json('response_body')->nullable()->comment('Response body (truncated if large)');
            $table->decimal('response_time', 8, 3)->nullable()->comment('Response time in milliseconds');
            
            // Client information
            $table->string('ip_address', 45)->comment('Client IP address (supports IPv6)');
            $table->string('user_agent', 500)->nullable()->comment('Client user agent string');
            
            // Error tracking
            $table->text('error_message')->nullable()->comment('Error message if request failed');
            $table->text('exception_trace')->nullable()->comment('Exception stack trace for debugging');
            
            // Categorization
            $table->string('ability_used', 100)->nullable()->comment('The scope/ability that was checked');
            $table->boolean('ability_granted')->default(true)->comment('Whether the ability check passed');
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for efficient querying
            $table->index('api_client_id');
            $table->index('method');
            $table->index('response_code');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index(['api_client_id', 'created_at']);
            $table->index(['response_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
