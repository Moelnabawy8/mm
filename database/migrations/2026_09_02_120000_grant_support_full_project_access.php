<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Give the support account the same reach over projects (departments) and
 * tasks that a company owner has.
 *
 * The account keeps its own type on purpose: switching an employee to type
 * "company" makes User::creatorId() return their own id, and every record
 * that belongs to the real owner would vanish for them. `access all projects`
 * is read by User::hasFullProjectAccess() instead, which the project / task /
 * timesheet / dashboard / report scoping now defers to.
 *
 * Permissions are granted directly to the user rather than to their role, so
 * nobody else sharing that role is elevated by accident.
 */
return new class extends Migration
{
    private string $email = 'support@my-communication.com';

    /** The new company-wide visibility switch. */
    private string $scopePermission = 'access all projects';

    /** Everything an admin can do to departments and tasks. */
    private array $permissions = [
        'manage project',
        'create project',
        'view project',
        'edit project',
        'delete project',
        'manage project task',
        'create project task',
        'view project task',
        'edit project task',
        'delete project task',
        'manage project task stage',
        'create project task stage',
        'edit project task stage',
        'delete project task stage',
        'manage task report',
    ];

    public function up(): void
    {
        Permission::firstOrCreate(['name' => $this->scopePermission, 'guard_name' => 'web']);

        $user = User::where('email', $this->email)->first();

        if (!$user) {
            // Nothing to grant yet - re-run this migration after the account exists.
            echo "  Skipped: no user with email {$this->email}\n";

            return;
        }

        foreach (array_merge([$this->scopePermission], $this->permissions) as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

            // Only the scope permission is ours to create; the rest ship with
            // the app, so a missing one means the name drifted - skip it
            // loudly instead of inventing a permission nothing checks.
            if (!$permission) {
                echo "  Skipped unknown permission: {$name}\n";
                continue;
            }

            if (!$user->hasPermissionTo($permission)) {
                $user->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $user = User::where('email', $this->email)->first();

        if ($user) {
            foreach (array_merge([$this->scopePermission], $this->permissions) as $name) {
                $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();

                if ($permission && $user->hasDirectPermission($permission)) {
                    $user->revokePermissionTo($permission);
                }
            }
        }

        Permission::where('name', $this->scopePermission)->where('guard_name', 'web')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
