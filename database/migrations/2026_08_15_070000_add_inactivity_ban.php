<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ختم النظام. وجوده يعني "نحن من حظرناه لغيابه" — وهو الشرط الوحيد الذي
            // يسمح بفكّ الحظر آليًّا، حتى لا نُعيد تفعيل حساب عطّله أدمن عمدًا من نفس
            // العمود (is_enable_login) الذي نستخدمه للمنع.
            $table->dateTime('inactivity_banned_at')->nullable()->after('last_login_at');

            // نافذة سماح تُمنح عند فكّ الحظر. بدونها فكّ الحظر كذبة: آخر دخول للمستخدم
            // ما زال قديمًا، فتشغيل الأسبوع التالي يحظره من جديد فورًا.
            $table->date('inactivity_exempt_until')->nullable()->after('inactivity_banned_at');

            $table->index('inactivity_banned_at');
        });

        Schema::create('inactivity_ban_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('action', 20);                       // ban | unban | exempt | report
            $table->string('reason', 191)->nullable();
            $table->dateTime('last_login_at')->nullable();      // لقطة الحالة وقت التنفيذ
            $table->unsignedInteger('idle_days')->nullable();
            $table->string('actor', 64)->default('system');     // system | user:<id>
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inactivity_ban_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['inactivity_banned_at']);
            $table->dropColumn(['inactivity_banned_at', 'inactivity_exempt_until']);
        });
    }
};
