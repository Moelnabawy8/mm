<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for commitments and meeting minutes.
 *
 * 'manage commitment' is the administrator: sees every commitment, creates one for
 * anybody. Everyone else reaches their own work through the two views that need no
 * permission at all — what I owe, and what is owed to me — because a commitment names
 * its owner and the person it is owed to, and those two people are exactly who should
 * see it. Gating "my own commitments" behind a permission would mean granting it to all
 * 68 employees, which is the same as not gating it while looking like it is gated.
 *
 * 'record meeting minutes' is separate from the existing 'manage meeting' (schedule a
 * meeting) because the person who books the room is rarely the person who writes up
 * what was decided.
 *
 * Mirrors 2026_09_12_150003_add_quality_review_permissions.php.
 */
return new class extends Migration
{
    private array $permissions = [
        'manage commitment',       // see and administer every commitment
        'create commitment',       // raise one for someone else
        'record meeting minutes',  // write up a meeting and turn decisions into commitments
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            if (!Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['company', 'super admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($this->permissions);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
