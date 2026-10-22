<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * A permission for the AI assistant.
 *
 * The chatbot routes were gated on `auth` alone, so every one of the 124 accounts could
 * spend against the OpenAI key without anybody granting them that. Now it takes a
 * permission, granted here to company and super admin only; an administrator widens it
 * deliberately.
 *
 * Mirrors 2026_09_13_100003_add_commitment_permissions.php.
 */
return new class extends Migration
{
    private array $permissions = [
        'use ai assistant',     // ask the assistant questions
        'manage ai assistant',  // see usage across the company, change the limit
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
