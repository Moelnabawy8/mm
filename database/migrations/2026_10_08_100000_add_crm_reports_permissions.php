<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the CRM Reports pages (Employee / Data / Financial).
 *
 * `show crm reports` opens the pages; on its own it shows the viewer only their own
 * numbers. `view all crm reports` widens that to every employee of the company. The
 * user types LeadScope treats as privileged, and Sales Managers (their sales group),
 * get the wide view without it, so the reports never show more or less than the
 * leads page does.
 *
 * `show crm reports` goes to every role that can already manage leads - an employee
 * who works leads can see their own report. `view all crm reports` goes to company
 * and super admin only; an administrator widens it deliberately.
 *
 * Mirrors 2026_09_16_100001_add_ai_assistant_permission.php. The names are NOT in
 * UsersTableSeeder::$arrPermissions on purpose (that insert is blind).
 */
return new class extends Migration
{
    private array $permissions = [
        'show crm reports',
        'view all crm reports',
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

        Role::whereHas('permissions', function ($q) {
            $q->where('name', 'manage lead');
        })->get()->each(function ($role) {
            $role->givePermissionTo('show crm reports');
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->where('guard_name', 'web')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
