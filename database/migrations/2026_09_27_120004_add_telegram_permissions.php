<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Mirrors 2026_07_25_050003_add_whatsapp_permissions: create the permissions AND
 * attach them to the `company` (id 2) and `super admin` roles, otherwise the
 * company admin gets a 403 on their own new page.
 */
return new class extends Migration
{
    private array $permissions = [
        'manage telegram notification',
        'manage telegram recipient',
        'view telegram log',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            if (!Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 'company' (id 2) owns the projects/tasks module; 'super admin' (id 1) manages the box.
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
