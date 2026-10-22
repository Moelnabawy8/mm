<?php

/**
 * open_september_features — make the September screens reachable by every role.
 *
 * Only the permissions the September links actually need, traced from the controller
 * that guards each one. Nothing else in the 113 the roles screen used to hide.
 *
 *   /quality                  review task quality      QualityReviewController@index
 *   /quality/report           view quality report      QualityReviewController@report
 *   /commitments              manage commitment        CommitmentController:35
 *   /commitments/create       create commitment        CommitmentController:285
 *   /commitments/mine         — none —                 scoped to the caller already
 *   /commitments/owed-to-me   — none —                 scoped to the caller already
 *   /meeting-minutes          record meeting minutes   MeetingMinutesController:210
 *   /meeting, /meeting-calender   manage meeting       MeetingController:18, :304
 *   /meeting/create           create meeting           MeetingController:48
 *   /chatbot                  use ai assistant         ChatController@index
 *   /report/inactivity        manage inactivity report
 *
 * Four neighbours are deliberately LEFT OUT. None of them is needed for any link above
 * to open, and each one hands over control rather than access:
 *
 *   manage quality criteria  — rewrites the criteria and names the reviewers, company wide
 *   manage ai assistant      — the assistant's own settings and spend cap
 *   manage inactivity ban    — bans accounts
 *   manage employee ban      — bans accounts
 *
 * Add them to a specific role by hand from /roles once the screens are in use.
 *
 *   php _fix/open_september_features.php --dry-run
 *   php _fix/open_september_features.php
 *   php _fix/open_september_features.php --include-client
 *
 * Nothing is ever revoked here.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$dry           = in_array('--dry-run', $argv, true);
$includeClient = in_array('--include-client', $argv, true);

$WANT = [
    'review task quality',
    'view quality report',
    'manage commitment',
    'create commitment',
    'record meeting minutes',
    'manage meeting',
    'create meeting',
    'use ai assistant',
    'manage inactivity report',
];

/** A customer logging in has no business on an internal quality or commitments board. */
$SKIP_ROLES = $includeClient ? [] : ['client'];

$line = str_repeat('=', 78);
echo "\n{$line}\n  OPEN THE SEPTEMBER SCREENS TO EVERY ROLE\n{$line}\n\n";

/* ------------------------------------------------------- which of them exist here */

$have = Permission::whereIn('name', $WANT)->pluck('id', 'name')->toArray();
$grant = [];

foreach ($WANT as $p) {
    if (isset($have[$p])) {
        $grant[] = $p;
        printf("  %-26s ok\n", $p);
    } else {
        printf("  %-26s NOT IN THE DATABASE — skipped\n", $p);
    }
}

if (!$grant) {
    fwrite(STDERR, "\n  none of them exist. Run 'php artisan migrate --force' first.\n");
    exit(1);
}

/* --------------------------------------------------------- roles that carry people */

$userCounts = DB::table('model_has_roles')
    ->select('role_id', DB::raw('COUNT(*) as n'))
    ->groupBy('role_id')
    ->pluck('n', 'role_id')
    ->toArray();

$roles = Role::orderBy('id')->get()->filter(fn($r) => ($userCounts[$r->id] ?? 0) > 0);

echo "\n  " . $roles->count() . " role(s) carry at least one user.\n\n";
printf("  %-4s %-32s %-6s %s\n", 'id', 'role', 'users', 'adding');
echo '  ' . str_repeat('-', 72) . "\n";

$granted = 0;
$touched = 0;
$skipped = [];

foreach ($roles as $role) {
    if (in_array(strtolower($role->name), $SKIP_ROLES, true)) {
        $skipped[] = $role->name . ' (' . ($userCounts[$role->id] ?? 0) . ' users)';
        continue;
    }

    $add = array_values(array_diff($grant, $role->permissions->pluck('name')->toArray()));

    printf("  %-4d %-32s %-6d %s\n", $role->id, mb_strimwidth($role->name, 0, 32),
        $userCounts[$role->id] ?? 0,
        $add ? count($add) : '—');

    if (!$add) {
        continue;
    }

    $granted += count($add);
    $touched++;

    if ($dry) {
        continue;
    }

    foreach ($add as $p) {
        $role->givePermissionTo($p);
    }
}

if ($skipped) {
    echo "\n  left alone: " . implode(', ', $skipped) . "\n";
    echo "  (pass --include-client to give these roles the screens too)\n";
}

echo "\n";

if (!$granted) {
    echo "Nothing to do — every role already has all of them.\n";
    exit(0);
}

if ($dry) {
    echo "dry run — nothing written ({$granted} grants across {$touched} role(s) would be made).\n";
    exit(0);
}

app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

echo "{$granted} grant(s) written across {$touched} role(s).\n";
echo "\nNow run:\n  php artisan optimize:clear\n";
echo "Then log out and back in — Spatie caches a user's permissions for the session.\n";
