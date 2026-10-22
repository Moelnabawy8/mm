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
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('name');
                // Add FK if leads table exists; ignore if it fails during some setups
                try {
                    $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
                } catch (\Throwable $e) {
                    // Some environments may not support adding the FK here; it's safe to ignore
                }
            }
            if (!Schema::hasColumn('deals', 'datetime')) {
                $table->dateTime('datetime')->nullable()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'lead_id')) {
                // Drop FK if present, then the column
                try {
                    $table->dropForeign(['lead_id']);
                } catch (\Throwable $e) {
                    // Ignore if FK does not exist
                }
                $table->dropColumn('lead_id');
            }
            if (Schema::hasColumn('deals', 'datetime')) {
                $table->dropColumn('datetime');
            }
        });
    }
};
