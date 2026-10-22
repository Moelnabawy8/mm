<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Follows database/migrations/2026_05_10_035702_add_new_module_permissions_2026.php, but
 * also attaches the permissions to the `company` role (id 2). That reference migration
 * creates permissions and never assigns them — without the assignment the company admin
 * gets a 403 on their own new page.
 */
return new class extends Migration
{
    private array $permissions = [
        'manage whatsapp notification',
        'manage whatsapp recipient',
        'view whatsapp log',
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
