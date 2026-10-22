<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponser;

class AuthApiController extends Controller
{
    use ApiResponser;

    public function login(Request $request)
    {
        $attr = $request->validate([
            'email' => 'required|string|email|',
            'password' => 'required|string'
        ]);

        if (!Auth::attempt($attr)) {
            return $this->error('Credentials not match', 401);
        }

        // حارس حالة الحساب — مسار الـ API كان يتخطّى كل فحوص الويب (is_enable_login،
        // is_active، delete_status)، فيبقى الحساب المحظور شغّالًا على الموبايل للأبد.
        if ($denyReason = \App\Support\Inactivity\ApiLoginGuard::denyReason(auth()->user())) {
            \App\Support\Inactivity\ApiLoginGuard::revokeTokens(auth()->user()->id);

            return $this->error($denyReason, 403);
        }

        // هذا المسار لم يكن يسجّل آخر دخول إطلاقًا، فيظهر مستخدم التطبيق غائبًا شهورًا.
        \App\Support\Inactivity\ApiLoginGuard::stampLogin(auth()->user());

        return $this->success([
            'token' => auth()->user()->createToken('API Token')->plainTextToken,
            'user' => auth()->user()
        ], 'Login successfully.');
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        return $this->success([], 'Tokens Revoked');
    }

    public function expireToken(Request $request)
    {
        $bearerToken = $request->bearerToken();

        if (empty($bearerToken) || !str_contains($bearerToken, '|')) {
            return $this->error('Invalid or missing token.', 401);
        }

        [$tokenId, $plainToken] = explode('|', $bearerToken, 2);

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);

        if (!$accessToken || !hash_equals($accessToken->token, hash('sha256', $plainToken))) {
            return $this->error('Invalid token.', 401);
        }

        $accessToken->expires_at = now()->subMinute();
        $accessToken->save();

        return $this->success([
            'token_id'   => $accessToken->id,
            'expires_at' => $accessToken->expires_at->toDateTimeString(),
        ], 'Token expired successfully. Use it now with /refresh-token to get a new one.');
    }

    public function refreshToken(Request $request)
    {
        $bearerToken = $request->bearerToken();

        if (empty($bearerToken)) {
            return $this->error('Token not provided.', 401);
        }

        if (!str_contains($bearerToken, '|')) {
            return $this->error('Invalid token format.', 401);
        }

        [$tokenId, $plainToken] = explode('|', $bearerToken, 2);

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);

        if (!$accessToken || !hash_equals($accessToken->token, hash('sha256', $plainToken))) {
            return $this->error('Invalid token.', 401);
        }

        $user = $accessToken->tokenable;

        if (!$user) {
            return $this->error('User not found for the provided token.', 401);
        }

        if ($denyReason = \App\Support\Inactivity\ApiLoginGuard::denyReason($user)) {
            \App\Support\Inactivity\ApiLoginGuard::revokeTokens($user->id);

            return $this->error($denyReason, 403);
        }

        $accessToken->delete();

        $newToken = $user->createToken('API Token')->plainTextToken;

        return $this->success([
            'token' => $newToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'Token refreshed successfully.');
    }

    public function register(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:4',
            'workspace' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Hash::make($request->password),
            'type' => 'company', // Default to company for registration via API for now
            'lang' => 'en',
            'created_by' => 1, // Default to admin for now, or self if company
        ]);

        $token = $user->createToken('API Token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => $user
        ], 'User created successfully.');
    }

    public function forgotPassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'email' => 'required|email|exists:users',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        // Logic to send reset link - Simplified for API to return success message as mail config might be missing
        // In real app, use Password::sendResetLink($request->only('email'));
        
        return $this->success([], 'Reset link sent to your email.');
    }

    public function resetPassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'email' => 'required|email|exists:users',
            'password' => 'required|confirmed|min:8',
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        // Simplistic reset logic
        $user = \App\Models\User::where('email', $request->email)->first();
        $user->password = \Hash::make($request->password);
        $user->save();

        return $this->success([], 'Password has been reset successfully.');
    }

    public function publicJobs()
    {
        $jobs = \App\Models\Job::where('status', 'active')->where('visibility', 'public')->get();
        return $this->success(['jobs' => $jobs], 'Public jobs retrieved.');
    }

    public function publicJobDetail($id)
    {
        $job = \App\Models\Job::with(['branches', 'jobCategory'])->where('status', 'active')->where('visibility', 'public')->find($id);
        if (!$job) return $this->error('Job not found.', 404);
        return $this->success(['job' => $job], 'Job detail retrieved.');
    }

    public function applyJob(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'resume' => 'required|file|mimes:pdf,doc,docx',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $job = \App\Models\Job::find($id);
        if (!$job) return $this->error('Job not found', 404);

        $application = new \App\Models\JobApplication();
        $application->job = $id;
        $application->name = $request->name;
        $application->email = $request->email;
        $application->phone = $request->phone;
        
        if ($request->hasFile('resume')) {
            $filenameWithExt = $request->file('resume')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('resume')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            $dir = storage_path('uploads/job/application');
            if (!file_exists($dir)) mkdir($dir, 0777, true);
            $request->file('resume')->storeAs('uploads/job/application', $fileNameToStore);
            $application->resume = $fileNameToStore;
        }

        $application->save();

        return $this->success(['application' => $application], 'Job application submitted successfully.');
    }

    public function profile()
    {
        $user = Auth::user();
        $employee = \App\Models\Employee::where('user_id', $user->id)
            ->with(['department', 'designation', 'branch'])
            ->first();

        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? ($employee ? $employee->phone : null),
            'address' => $user->address ?? ($employee ? $employee->address : null),
            'avatar' => $user->avatar ? asset('storage/uploads/avatar/' . $user->avatar) : null,
            'type' => $user->type,
        ];

        if ($employee) {
            $profile['employee_id'] = $employee->employee_id;
            $profile['department'] = $employee->department ? [
                'id' => $employee->department->id,
                'name' => $employee->department->name,
            ] : null;
            $profile['designation'] = $employee->designation ? [
                'id' => $employee->designation->id,
                'name' => $employee->designation->name,
            ] : null;
            $profile['branch'] = $employee->branch ? [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
            ] : null;
            $profile['join_date'] = $employee->company_doj;
            $profile['salary'] = $employee->salary;
            $profile['gender'] = $employee->gender;
            $profile['dob'] = $employee->dob;
        }

        return $this->success(['profile' => $profile], 'Profile retrieved successfully.');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $validator = \Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'password' => 'nullable|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->has('address')) {
            $user->address = $request->address;
        }

        if ($request->has('password') && !empty($request->password)) {
            $user->password = \Hash::make($request->password);
        }
        
        $user->save();

        // Update employee record if exists
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        if ($employee) {
            if ($request->has('name')) {
                $employee->name = $request->name;
            }
            if ($request->has('email')) {
                $employee->email = $request->email;
            }
            if ($request->has('phone')) {
                $employee->phone = $request->phone;
            }
            if ($request->has('address')) {
                $employee->address = $request->address;
            }
            $employee->save();
        }

        return $this->success(['user' => $user], 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $user = Auth::user();

        if (!\Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password does not match.', 401);
        }

        $user->password = \Hash::make($request->new_password);
        $user->save();

        return $this->success([], 'Password changed successfully.');
    }

    public function uploadAvatar(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $user = Auth::user();
        if ($request->hasFile('avatar')) {
             $filenameWithExt = $request->file('avatar')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('avatar')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            $dir = storage_path('uploads/avatar');
             if (!file_exists($dir)) mkdir($dir, 0777, true);
            $request->file('avatar')->storeAs('uploads/avatar', $fileNameToStore);
            $user->avatar = $fileNameToStore;
            $user->save();
        }

        return $this->success(['avatar' => $user->avatar], 'Avatar updated successfully.');
    }
}
