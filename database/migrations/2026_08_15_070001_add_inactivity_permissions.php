<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dedicated permissions for the inactivity report.
 *
 * Reusing the existing `manage user` / `edit user` would have been the smaller change, but
 * the `Employee` role already holds both — so every one of the 29 employees would have been
 * able to read the whole company's login history and lift bans. These two permissions go to
 * `company` and `super admin` only.
 *
 * Mirrors 2026_07_25_050003_add_whatsapp_permissions.php.
 */
return new class extends Migration
{
    private array $permissions = [
        'manage inactivity report',   // view the page
        'manage inactivity ban',      // unban, exempt, change the rules, run on demand
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
