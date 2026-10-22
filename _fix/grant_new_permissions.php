<?php

/**
 * grant_new_permissions — put the eight September permissions back on a role.
 *
 * The roles screen could not draw a checkbox for them, and RoleController@update revokes
 * every permission before re-granting the ones the form posted, so saving any role wiped
 * them. fix_role_permission_screen.php stops that happening again; this puts back what
 * was already lost, and is also the way to hand the new screens to a supervisor.
 *
 *   php _fix/grant_new_permissions.php --dry-run
 *   php _fix/grant_new_permissions.php                       # company + super admin
 *   php _fix/grant_new_permissions.php --role="Team Leader"  # and this role too
 *   php _fix/grant_new_permissions.php --role="Team Leader" --only=quality,commitment
 *
 * Groups for --only:  quality  commitment  meeting  ai
 * Nothing is ever revoked here.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$dry = in_array('--dry-run', $argv, true);

$GROUPS = [
    'quality'    => ['manage quality criteria', 'review task quality', 'view quality report'],
    'commitment' => ['manage commitment', 'create commitment'],
    'meeting'    => ['record meeting minutes'],
    'ai'         => ['use ai assistant', 'manage ai assistant'],
];

$roleNames = ['company', 'super admin'];
$only      = array_keys($GROUPS);

foreach ($argv as $a) {
    if (strpos($a, '--role=') === 0) {
        $roleNames[] = trim(substr($a, 7), " \"'");
    }

    if (strpos($a, '--only=') === 0) {
        $only = array_filter(array_map('trim', explode(',', substr($a, 7))));
    }
}

$wanted = [];

foreach ($only as $g) {
    if (!isset($GROUPS[$g])) {
        fwrite(STDERR, "  unknown group '{$g}'. Known: " . implode(', ', array_keys($GROUPS)) . "\n");
        exit(1);
    }

    $wanted = array_merge($wanted, $GROUPS[$g]);
}

$roleNames = array_values(array_unique($roleNames));

echo "\n  granting : " . implode(', ', $wanted) . "\n";
echo "  to roles : " . implode(', ', $roleNames) . "\n\n";

/* ------------------------------------------------------ the permissions must exist */

$missing = array_values(array_diff($wanted, Permission::whereIn('name', $wanted)->pluck('name')->toArray()));

if ($missing) {
    fwrite(STDERR, "  these permissions are not in the database: " . implode(', ', $missing) . "\n");
    fwrite(STDERR, "  run 'php artisan migrate --force' first — refusing.\n");
    exit(1);
}

/* ------------------------------------------------------------------------ grant */

$granted = 0;
$touched = 0;

foreach ($roleNames as $name) {
    $roles = Role::where('name', $name)->get();

    if ($roles->isEmpty()) {
        echo "  no role named '{$name}' — skipped.\n";
        continue;
    }

    foreach ($roles as $role) {
        // Several companies can each own a role of the same name; every one of them
        // needs the grant, so this loops rather than taking first().
        $add = array_values(array_diff($wanted, $role->permissions->pluck('name')->toArray()));

        if (!$add) {
            printf("  role %-24s (id %-4d) already has all %d\n", $role->name, $role->id, count($wanted));
            continue;
        }

        printf("  role %-24s (id %-4d) adding %d: %s\n", $role->name, $role->id, count($add), implode(', ', $add));
        $touched++;
        $granted += count($add);

        if ($dry) {
            continue;
        }

        foreach ($add as $p) {
            $role->givePermissionTo($p);
        }
    }
}

echo "\n";

if (!$granted) {
    echo "Nothing to do — every listed role already holds every listed permission.\n";
    exit(0);
}

if ($dry) {
    echo "dry run — nothing written ({$granted} grants across {$touched} role(s) would be made).\n";
    exit(0);
}

app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

echo "{$granted} grant(s) written across {$touched} role(s).\n";
echo "\nNow run:\n  php artisan optimize:clear\n";
echo "Then log out and back in on the account you are testing with.\n";
