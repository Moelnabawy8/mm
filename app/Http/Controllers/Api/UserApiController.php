<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Utility;
use App\Models\CustomField;
use Spatie\Permission\Models\Role;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserApiController extends Controller
{
    use ApiResponser;

    public function getUsers()
    {
        $user = Auth::user();
        if (Auth::user()->type == 'super admin') {
            $users = User::where('created_by', '=', $user->creatorId())->where('type', '=', 'company')->with(['currentPlan'])->get();
        } else {
            $users = User::where('created_by', '=', $user->creatorId())->where('type', '!=', 'client')->with(['currentPlan'])->get();
        }
        return $this->success(['users' => $users], 'Users retrieved.');
    }

    public function createUser(Request $request)
    {
        $default_language = DB::table('settings')->select('value')->where('name', 'default_language')->where('created_by', '=', Auth::user()->creatorId())->first();
        $objUser = Auth::user()->creatorId();

        if (Auth::user()->type == 'super admin') {
            $validator = Validator::make($request->all(), [
                'name' => 'required|max:120',
                'email' => 'required|email|unique:users',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            $enableLogin = 0;
            if (!empty($request->password_switch) && $request->password_switch == 'on') {
                $enableLogin = 1;
                $validator = Validator::make($request->all(), ['password' => 'required|min:6']);

                if ($validator->fails()) {
                    return $this->error($validator->errors()->first(), 422);
                }
            }

            $userpassword = $request->input('password');
            $settings = Utility::settings();

            do {
                $code = rand(100000, 999999);
            } while (User::where('referral_code', $code)->exists());

            $user = new User();
            $user['name'] = $request->name;
            $user['email'] = $request->email;
            $user['password'] = !empty($userpassword) ? Hash::make($userpassword) : null;
            $user['type'] = 'company';
            $user['default_pipeline'] = 1;
            $user['plan'] = 1;
            $user['lang'] = !empty($default_language) ? $default_language->value : 'en';
            $user['referral_code'] = $code;
            $user['created_by'] = Auth::user()->creatorId();
            $user['plan'] = Plan::first()->id;
            if ($settings['email_verification'] == 'on') {
                $user['email_verified_at'] = null;
            } else {
                $user['email_verified_at'] = date('Y-m-d H:i:s');
            }
            $user['is_enable_login'] = $enableLogin;
            $user->save();

            $role_r = Role::findByName('company');
            $user->assignRole($role_r);
            $user->userDefaultDataRegister($user->id);
            $user->userWarehouseRegister($user->id);
            $user->userDefaultBankAccount($user->id);
            Utility::chartOfAccountTypeData($user->id);
            Utility::chartOfAccountData1($user->id);
            Utility::pipeline_lead_deal_Stage($user->id);
            Utility::project_task_stages($user->id);
            Utility::labels($user->id);
            Utility::sources($user->id);
            Utility::jobStage($user->id);

            return $this->success(['user' => $user], 'Company created successfully.');
        } else {
            $validator = Validator::make($request->all(), [
                'name' => 'required|max:120',
                'email' => 'required|email|unique:users',
                'role' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }

            $enableLogin = 0;
            if (!empty($request->password_switch) && $request->password_switch == 'on') {
                $enableLogin = 1;
                $validator = Validator::make($request->all(), ['password' => 'required|min:6']);

                if ($validator->fails()) {
                    return $this->error($validator->errors()->first(), 422);
                }
            }

            $objUser = User::find($objUser);
            $total_user = $objUser->countUsers();
            $plan = Plan::find($objUser->plan);
            $userpassword = $request->input('password');

            if ($total_user < $plan->max_users || $plan->max_users == -1) {
                $role_r = Role::findById($request->role);
                $userData = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => !empty($userpassword) ? Hash::make($userpassword) : null,
                    'type' => $role_r->name,
                    'lang' => !empty($default_language) ? $default_language->value : 'en',
                    'created_by' => Auth::user()->creatorId(),
                    'email_verified_at' => date('Y-m-d H:i:s'),
                    'is_enable_login' => $enableLogin,
                ];

                $user = User::create($userData);
                $user->assignRole($role_r);
                if ($user->type != 'client') {
                    Utility::employeeDetails($user->id, Auth::user()->creatorId());
                }

                return $this->success(['user' => $user], 'User created successfully.');
            } else {
                return $this->error('Your user limit is over, Please upgrade plan.', 400);
            }
        }
    }

    public function getUser($id)
    {
        $user = User::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['currentPlan', 'roles'])->first();
        if (!$user) {
            return $this->error('User not found.', 404);
        }
        return $this->success(['user' => $user], 'User detail.');
    }

    public function updateUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:120',
            'email' => 'required|email|unique:users,email,' . $id,
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = User::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->name = $request->name;
        $user->email = $request->email;

        if (!empty($request->password)) {
            $validator = Validator::make($request->all(), ['password' => 'required|min:6']);
            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }
            $user->password = Hash::make($request->password);
        }

        if (!empty($request->role)) {
            $role_r = Role::findById($request->role);
            $user->syncRoles([$role_r]);
        }

        $user->save();

        return $this->success(['user' => $user], 'User updated successfully.');
    }

    public function deleteUser($id)
    {
        $user = User::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$user) {
            return $this->error('User not found.', 404);
        }
        $user->delete();
        return $this->success([], 'User deleted successfully.');
    }

    public function resetUserPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = User::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return $this->success([], 'Password reset successfully.');
    }

    public function getRoles()
    {
        $user = Auth::user();
        $roles = Role::where('created_by', '=', $user->creatorId())->where('name', '!=', 'client')->get();
        return $this->success(['roles' => $roles], 'Roles retrieved.');
    }
}
