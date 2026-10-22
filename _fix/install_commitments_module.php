<?php

/**
 * install_commitments_module — wire up commitments (tasks 2 and 3) and meeting minutes
 * (task 4).
 *
 * Everything else in the package is new files. Three shared files are touched, and all
 * three by exact-anchor replacement, never by shipping a whole copy:
 *
 *   routes/web.php                       — 11 route definitions
 *   resources/views/partials/admin/menu.blade.php — 2 menu entries
 *   app/Models/Meeting.php               — the model gains the new columns and relations
 *
 * The Meeting model matters most. It has been in this repo since 2020 and a deploy that
 * overwrote a shared file on 2026-09-12 silently deleted 18 routes and 174 translation
 * keys. So its edit is anchored on the exact fillable array it has today, and the
 * script refuses to run if that array is not found verbatim — if somebody has edited
 * the model since, this stops rather than guesses.
 *
 *   php _fix/install_commitments_module.php --dry-run
 *   php _fix/install_commitments_module.php
 */

$dry  = in_array('--dry-run', $argv, true);
$root = __DIR__ . '/..';

/* --------------------------------------------------------------------- routes */

$routesBlock = <<<'BLOCK'

// Commitments (tasks 2 and 3) and meeting minutes (task 4). One feature: a commitment
// is owed by an employee or a department to a named person by a date; a meeting's
// decisions become commitments. See the commitments migration for why owed_to_id lives
// on the commitment rather than as a reporting line on employees.
Route::middleware(['auth', 'XSS'])->group(function () {
    Route::get('commitments', [\App\Http\Controllers\CommitmentController::class, 'index'])
        ->name('commitments.index');
    Route::get('commitments/mine', [\App\Http\Controllers\CommitmentController::class, 'mine'])
        ->name('commitments.mine');
    Route::get('commitments/owed-to-me', [\App\Http\Controllers\CommitmentController::class, 'owedToMe'])
        ->name('commitments.owed');
    Route::get('commitments/create', [\App\Http\Controllers\CommitmentController::class, 'create'])
        ->name('commitments.create');
    Route::post('commitments', [\App\Http\Controllers\CommitmentController::class, 'store'])
        ->name('commitments.store');
    Route::get('commitments/{id}', [\App\Http\Controllers\CommitmentController::class, 'show'])
        ->name('commitments.show')->whereNumber('id');
    Route::get('commitments/{id}/edit', [\App\Http\Controllers\CommitmentController::class, 'edit'])
        ->name('commitments.edit')->whereNumber('id');
    Route::post('commitments/{id}', [\App\Http\Controllers\CommitmentController::class, 'update'])
        ->name('commitments.update')->whereNumber('id');
    Route::post('commitments/{id}/update', [\App\Http\Controllers\CommitmentController::class, 'addUpdate'])
        ->name('commitments.update.add')->whereNumber('id');
    Route::post('commitments/{id}/delete', [\App\Http\Controllers\CommitmentController::class, 'destroy'])
        ->name('commitments.destroy')->whereNumber('id');

    Route::get('meeting-minutes', [\App\Http\Controllers\MeetingMinutesController::class, 'board'])
        ->name('meetings.board');
    Route::get('meeting-minutes/{id}', [\App\Http\Controllers\MeetingMinutesController::class, 'edit'])
        ->name('meetings.minutes')->whereNumber('id');
    Route::post('meeting-minutes/{id}', [\App\Http\Controllers\MeetingMinutesController::class, 'save'])
        ->name('meetings.minutes.save')->whereNumber('id');
    Route::post('meeting-minutes/{id}/decision', [\App\Http\Controllers\MeetingMinutesController::class, 'addDecision'])
        ->name('meetings.decision')->whereNumber('id');
});

BLOCK;

/* ----------------------------------------------------------------------- menu */

$menuBlock = <<<'BLOCK'
                                @if (Gate::check('manage commitment') || Gate::check('create commitment') || Auth::check())
                                    <li class="dash-item {{ Request::segment(1) == 'commitments' ? 'active' : '' }}">
                                        <a class="dash-link" href="{{ route('commitments.mine') }}">
                                            <i class="ti ti-checklist me-1"></i>{{ __('Commitments') }}
                                        </a>
                                    </li>
                                @endif
                                @if (Gate::check('record meeting minutes') || Gate::check('manage meeting') || Gate::check('manage commitment'))
                                    <li class="dash-item {{ Request::segment(1) == 'meeting-minutes' ? 'active' : '' }}">
                                        <a class="dash-link" href="{{ route('meetings.board') }}">
                                            <i class="ti ti-notes me-1"></i>{{ __('Meeting Minutes') }}
                                        </a>
                                    </li>
                                @endif
BLOCK;

/* ---------------------------------------------------------------- Meeting.php */

// The model exactly as it stands today. Anything else means somebody has changed it.
$meetingOld = <<<'OLD'
class Meeting extends Model
{
    protected $fillable = [
        'branch_id',
        'department_id',
        'employee_id',
        'title',
        'date',
        'time',
        'note',
        'created_by',
    ];
}
OLD;

$meetingNew = <<<'NEW'
class Meeting extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_HELD      = 'held';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'branch_id',
        'department_id',
        'employee_id',
        'title',
        'date',
        'time',
        'note',
        'status',
        'minutes',
        'minutes_by',
        'minutes_at',
        'created_by',
    ];

    protected $casts = [
        'date'       => 'date',
        'minutes_at' => 'datetime',
    ];

    /** Commitments raised out of this meeting's minutes. */
    public function commitments()
    {
        return $this->hasMany(Commitment::class, 'meeting_id', 'id');
    }

    public function minutesAuthor()
    {
        return $this->belongsTo(User::class, 'minutes_by', 'id');
    }

    /**
     * department_id and employee_id are longText holding comma-separated id lists, not
     * foreign keys - that is how this table was built in 2020 and changing it would
     * break the existing controller, calendar and views. These read them so the rest of
     * the code never has to remember.
     */
    public function employeeIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->employee_id))));
    }

    public function departmentIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->department_id))));
    }

    public function employees()
    {
        $ids = $this->employeeIds();

        return $ids ? Employee::whereIn('id', $ids)->get() : collect();
    }

    public function hasMinutes(): bool
    {
        return trim(strip_tags((string) $this->minutes)) !== '';
    }

    /**
     * A meeting whose date has passed with nothing written up. This is the number
     * task 4 exists to drive down, so it is defined once, here.
     */
    public function isAwaitingMinutes(): bool
    {
        if ($this->status === self::STATUS_CANCELLED || $this->hasMinutes()) {
            return false;
        }

        return $this->date !== null && $this->date->endOfDay()->isPast();
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 'secondary';
        }

        if ($this->hasMinutes()) {
            return 'success';
        }

        return $this->isAwaitingMinutes() ? 'danger' : 'info';
    }
}
NEW;

/* ------------------------------------------------------------------- schedule */

$scheduleLine = "Schedule::command('commitments:weekly-roll')->weeklyOn(1, '07:00')->withoutOverlapping();";

/* -------------------------------------------------------------------- helpers */

function loadSource(string $path): array
{
    $raw = file_get_contents($path);

    return [str_replace("\r\n", "\n", $raw), strpos($raw, "\r\n") !== false ? "\r\n" : "\n"];
}

function saveSource(string $path, string $src, string $eol, bool $lint): bool
{
    $out = str_replace("\n", $eol, $src);

    if ($lint) {
        $tmp = sys_get_temp_dir() . '/ic_' . getmypid() . '_' . mt_rand() . '.php';
        file_put_contents($tmp, $out);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lines, $rc);
        unlink($tmp);

        if ($rc !== 0) {
            fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $lines) . "\n");

            return false;
        }
    }

    copy($path, $path . '.bak-' . date('Ymd-His'));
    file_put_contents($path, $out);
    clearstatcache();

    return true;
}

$applied = 0;

/* ------------------------------------------------------------------ 1. routes */

$file = $root . '/routes/web.php';
[$src, $eol] = loadSource($file);

if (strpos($src, "->name('commitments.index')") !== false) {
    echo "  already present  routes\n";
} else {
    $anchor = "Route::resource('department', DepartmentController::class)->middleware(['auth', 'XSS']);";

    if (substr_count($src, $anchor) !== 1) {
        fwrite(STDERR, "  routes anchor not found exactly once — refusing.\n");
        exit(1);
    }

    $src = str_replace($anchor, $anchor . "\n" . $routesBlock, $src);
    echo "  patched          routes (14 definitions)\n";

    if (!$dry && !saveSource($file, $src, $eol, true)) {
        exit(1);
    }

    $applied++;
}

/* -------------------------------------------------------------------- 2. menu */

$file = $root . '/resources/views/partials/admin/menu.blade.php';
[$src, $eol] = loadSource($file);

if (strpos($src, "route('commitments.mine')") !== false) {
    echo "  already present  menu\n";
} else {
    // Sit beside the quality entry, which is already outside any @can gate.
    $anchor = "                                @if (Gate::check('manage quality criteria') || Gate::check('review task quality') || Gate::check('view quality report'))";

    if (substr_count($src, $anchor) !== 1) {
        fwrite(STDERR, "  menu anchor not found exactly once — is the quality module installed? Refusing.\n");
        exit(1);
    }

    $src = str_replace($anchor, $menuBlock . "\n" . $anchor, $src);
    echo "  patched          menu (2 entries)\n";

    if (!$dry) {
        foreach ([['@if(', '@if ', '@endif'], ['@can(', '@can ', '@endcan']] as [$a, $b, $end]) {
            if (substr_count($src, $a) + substr_count($src, $b) !== substr_count($src, $end)) {
                fwrite(STDERR, "  blade directives would not balance — nothing written.\n");
                exit(1);
            }
        }

        if (!saveSource($file, $src, $eol, false)) {
            exit(1);
        }
    }

    $applied++;
}

/* ------------------------------------------------------------- 3. Meeting.php */

$file = $root . '/app/Models/Meeting.php';
[$src, $eol] = loadSource($file);

if (strpos($src, 'function isAwaitingMinutes') !== false) {
    echo "  already present  Meeting model\n";
} else {
    if (substr_count($src, $meetingOld) !== 1) {
        fwrite(STDERR, "  app/Models/Meeting.php is not in its expected state — refusing to touch it.\n"
                     . "  Expected the original 2020 class body verbatim. Somebody has edited it;\n"
                     . "  apply the change by hand rather than letting this guess.\n");
        exit(1);
    }

    $src = str_replace($meetingOld, $meetingNew, $src);
    echo "  patched          Meeting model (columns, relations, minutes helpers)\n";

    if (!$dry && !saveSource($file, $src, $eol, true)) {
        exit(1);
    }

    $applied++;
}

/* ---------------------------------------------------------------- 4. schedule */

$file = $root . '/routes/console.php';
[$src, $eol] = loadSource($file);

if (strpos($src, 'commitments:weekly-roll') !== false) {
    echo "  already present  weekly schedule\n";
} else {
    // Laravel 11: app/Console/Kernel.php is never loaded, so a schedule placed there
    // runs silently never. See [[laravel11-kernel-schedule-dead]].
    $src = rtrim($src) . "\n\n"
         . "// Monday: close last week's commitments, roll recurring ones forward, and report\n"
         . "// what is overdue and which meetings still have no minutes.\n"
         . $scheduleLine . "\n";

    echo "  patched          routes/console.php (weekly roll, Mondays 07:00)\n";

    if (!$dry && !saveSource($file, $src, $eol, true)) {
        exit(1);
    }

    $applied++;
}

echo "\n";

if (!$applied) {
    echo "Nothing to do.\n";
    exit(0);
}

echo $dry
    ? "dry run — nothing written.\n"
    : "Now run:\n  php artisan migrate --force\n  php artisan optimize:clear\n";
