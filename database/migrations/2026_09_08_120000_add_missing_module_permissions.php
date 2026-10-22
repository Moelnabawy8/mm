<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates permissions the code already gates on but that were never inserted
 * into the `permissions` table. Because the record was missing, can() returned
 * false for every role — including the company owner — so the guarded UI could
 * never appear no matter how many boxes were ticked in /roles.
 *
 * Each permission is granted to the roles that already hold its natural sibling,
 * so nobody gains access to a module they could not already work in.
 *
 * NOTE: these names are deliberately NOT added to UsersTableSeeder's
 * $arrPermissions — that array is inserted blind, so listing them there would
 * create duplicate rows on a fresh install. Adding them to the role permission
 * lists further down the seeder is safe (those match by name).
 */
return new class extends Migration
{
    /**
     * permission name => sibling permission whose holders should inherit it.
     * A null sibling means: create the record but grant it to nobody.
     */
    private array $permissions = [
        // The whole action column in setsalary/edit.blade.php is wrapped in
        // @can('delete set salary'), so the edit buttons inside it never render.
        'delete set salary' => 'edit set salary',

        // trainer/index.blade.php gates the view button on this.
        'show trainer' => 'manage trainer',

        // ChartOfAccountTypeController gates every action on these four.
        'manage constant chart of account type' => 'manage chart of account',
        'create constant chart of account type' => 'create chart of account',
        'edit constant chart of account type'   => 'edit chart of account',
        'delete constant chart of account type' => 'delete chart of account',

        // Deliberately granted to NO role: this one widens data visibility
        // (without it a user only sees leads assigned to them). Creating the
        // record makes it assignable in /roles; who gets it is the company's
        // call, not a migration's.
        'view all leads' => null,
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name => $sibling) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

            if (!$permission) {
                $permission = Permission::create(['name' => $name, 'guard_name' => 'web']);
            }

            if ($sibling === null) {
                continue;
            }

            $roles = Role::whereHas('permissions', function ($q) use ($sibling) {
                $q->where('name', $sibling);
            })->get();

            foreach ($roles as $role) {
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys($this->permissions))
            ->where('guard_name', 'web')
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
