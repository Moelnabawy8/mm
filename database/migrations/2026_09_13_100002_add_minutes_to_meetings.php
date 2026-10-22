<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minutes, attendance and an outcome for the existing meetings module.
 *
 * The module has had full CRUD, a calendar and four views since 2020 and holds ZERO
 * rows on production. It was never missing screens; it was missing a reason to open it.
 * Task 4 supplies that reason — "ensure minutes are recorded" — so this extends what is
 * already wired into the menu and routes rather than adding a second meetings module
 * beside a dead one.
 *
 * `note` already exists and is the organiser's note written when scheduling. Minutes
 * are a different thing written afterwards by a different person, so they get their own
 * columns rather than overloading it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'status')) {
                $table->enum('status', ['scheduled', 'held', 'cancelled'])
                      ->default('scheduled')->after('note');
            }

            if (!Schema::hasColumn('meetings', 'minutes')) {
                $table->longText('minutes')->nullable()->after('status');
            }

            if (!Schema::hasColumn('meetings', 'minutes_by')) {
                $table->unsignedBigInteger('minutes_by')->nullable()->after('minutes');
            }

            if (!Schema::hasColumn('meetings', 'minutes_at')) {
                $table->timestamp('minutes_at')->nullable()->after('minutes_by');
            }
        });

        if (!Schema::hasColumn('meeting_employees', 'attended')) {
            Schema::table('meeting_employees', function (Blueprint $table) {
                // Null = not marked yet, which is different from "did not attend".
                $table->boolean('attended')->nullable()->after('employee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            foreach (['status', 'minutes', 'minutes_by', 'minutes_at'] as $c) {
                if (Schema::hasColumn('meetings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        if (Schema::hasColumn('meeting_employees', 'attended')) {
            Schema::table('meeting_employees', function (Blueprint $table) {
                $table->dropColumn('attended');
            });
        }
    }
};
