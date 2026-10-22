<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('email');
            $table->string('country')->nullable()->after('gender');
            $table->string('city')->nullable()->after('country');
            $table->string('costing_type')->nullable()->after('city');
            $table->string('member_id')->nullable()->after('costing_type');
            $table->string('campaign_name')->nullable()->after('member_id');
            $table->decimal('cost', 15, 2)->default(0.00)->after('campaign_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['gender', 'country', 'city', 'costing_type', 'member_id', 'campaign_name', 'cost']);
        });
    }
};
