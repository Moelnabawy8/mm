<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private string $permission = 'manage employee ban';

    public function up(): void
    {
        $permission = Permission::where('name', $this->permission)->where('guard_name', 'web')->first();

        if (!$permission) {
            $permission = Permission::create(['name' => $this->permission, 'guard_name' => 'web']);
        }

        // Anyone who already administers users can lift a ban.
        $roles = Role::whereHas('permissions', function ($q) {
            $q->where('name', 'manage user');
        })->get();

        foreach ($roles as $role) {
            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
