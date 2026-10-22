<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the per-department quality section.
 *
 * Kept separate from the existing `manage quality evaluation` family, which belongs to
 * the lead-qualification module (49,069 rows keyed on lead_id) and is already granted to
 * Sales Employee and six other roles. Reusing it would hand 30 sales staff the ability to
 * score the Developers department's deliverables.
 *
 * Only 'manage quality criteria' is granted here. 'review task quality' is deliberately
 * left unassigned: who reviews a department is chosen by name on the department's quality
 * screen, and granting the permission to a role up front would contradict that. An admin
 * grants it to whichever roles their reviewers hold.
 *
 * Mirrors 2026_08_15_070001_add_inactivity_permissions.php.
 */
return new class extends Migration
{
    private array $permissions = [
        'manage quality criteria',   // define criteria, name reviewers, see every department
        'review task quality',       // score a completed task in a department you review
        'view quality report',       // read the cross-department report
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
