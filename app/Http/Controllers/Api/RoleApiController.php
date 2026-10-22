<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use App\Traits\ApiResponser;

class RoleApiController extends Controller
{
    use ApiResponser;

    public function getRoles()
    {
        $roles = Role::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['roles' => $roles], 'Roles retrieved.');
    }

    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:100|unique:roles,name,NULL,id,created_by,' . Auth::user()->creatorId(),
            'permissions' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $role = new Role();
        $role->name = $request->name;
        $role->created_by = Auth::user()->creatorId();
        $role->save();

        // Assign permissions to role
        foreach ($request->permissions as $permission) {
            $p = Permission::where('id', '=', $permission)->first();
            if ($p) {
                $role->givePermissionTo($p);
            }
        }

        return $this->success(['role' => $role], 'Role created successfully.');
    }

    public function getRole($id)
    {
        $role = Role::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with('permissions')
            ->first();

        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        return $this->success(['role' => $role], 'Role detail.');
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:100|unique:roles,name,' . $id . ',id,created_by,' . Auth::user()->creatorId(),
            'permissions' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $role->name = $request->name;
        $role->save();

        // Revoke all permissions and reassign
        $allPermissions = Permission::all();
        foreach ($allPermissions as $p) {
            $role->revokePermissionTo($p);
        }

        foreach ($request->permissions as $permission) {
            $p = Permission::where('id', '=', $permission)->first();
            if ($p) {
                $role->givePermissionTo($p);
            }
        }

        return $this->success(['role' => $role], 'Role updated successfully.');
    }

    public function deleteRole($id)
    {
        $role = Role::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        $role->delete();

        return $this->success([], 'Role deleted successfully.');
    }

    public function getPermissions()
    {
        $user = Auth::user();

        if ($user->type == 'super admin') {
            $permissions = Permission::all()->pluck('name', 'id');
        } else {
            $permissions = new Collection();
            foreach ($user->roles as $role) {
                $permissions = $permissions->merge($role->permissions);
            }
            $permissions = $permissions->pluck('name', 'id');
        }

        return $this->success(['permissions' => $permissions], 'Permissions retrieved.');
    }

    public function getRoleUsers($id)
    {
        $role = Role::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        $users = $role->users()->get();

        return $this->success(['users' => $users], 'Role users retrieved.');
    }

    public function assignRoleToUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $role = Role::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        $user = \App\Models\User::find($request->user_id);
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Check if user belongs to the same company
        if ($user->created_by != Auth::user()->creatorId()) {
            return $this->error('User not found or access denied.', 404);
        }

        $user->assignRole($role);

        return $this->success([], 'Role assigned to user successfully.');
    }

    public function removeRoleFromUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $role = Role::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        $user = \App\Models\User::find($request->user_id);
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Check if user belongs to the same company
        if ($user->created_by != Auth::user()->creatorId()) {
            return $this->error('User not found or access denied.', 404);
        }

        $user->removeRole($role);

        return $this->success([], 'Role removed from user successfully.');
    }
}
