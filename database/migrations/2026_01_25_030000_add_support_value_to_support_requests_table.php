<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->boolean('client_subscribed')->nullable()->after('response_notes');
            $table->decimal('subscription_amount', 10, 2)->nullable()->after('client_subscribed');
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            $table->dropColumn(['client_subscribed', 'subscription_amount']);
        });
    }
};
