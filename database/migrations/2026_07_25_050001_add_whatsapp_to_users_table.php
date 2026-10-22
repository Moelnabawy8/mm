<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp number per user, managed by the company admin only.
 *
 * NOTE: `whatsapp` is deliberately NOT added to User::$fillable — UserController@editprofile
 * uses fill($input)->save(), which would let an employee change their own number. The number
 * is assigned explicitly in the admin-controlled write paths instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'whatsapp')) {
                $table->string('whatsapp', 32)->nullable()->after('email')
                    ->comment('E.164 WhatsApp number, e.g. +962791234567');
            }
            if (!Schema::hasColumn('users', 'whatsapp_status')) {
                $table->enum('whatsapp_status', ['unset', 'valid', 'invalid'])
                    ->default('unset')->after('whatsapp')
                    ->comment('Driven by real send results; invalid = Meta error 131026');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'whatsapp_status']);
        });
    }
};
