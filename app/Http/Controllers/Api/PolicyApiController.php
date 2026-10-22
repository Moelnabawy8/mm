<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyPolicy;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PolicyApiController extends Controller
{
    use ApiResponser;

    public function getPolicies()
    {
        $employee_branch = Employee::where('user_id', Auth::user()->id)->pluck('branch_id');

        if (Auth::user()->type == 'Employee' && count($employee_branch) > 0) {
            $policies = CompanyPolicy::where('created_by', '=', Auth::user()->creatorId())
                ->where('branch', $employee_branch)
                ->with('branches')
                ->get();
        } else {
            $policies = CompanyPolicy::where('created_by', '=', Auth::user()->creatorId())
                ->with('branches')
                ->get();
        }

        return $this->success(['policies' => $policies], 'Policies retrieved.');
    }

    public function createPolicy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required|exists:branches,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,zip|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        $policy = new CompanyPolicy();
        $policy->branch = $request->branch;
        $policy->title = $request->title;
        $policy->description = $request->description;
        $policy->created_by = Auth::user()->creatorId();

        // Handle file upload
        if ($request->hasFile('attachment')) {
            $filenameWithExt = $request->file('attachment')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('attachment')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $dir = 'uploads/companyPolicy/';
            $image_path = $dir . $fileNameToStore;

            // Delete existing file if exists
            if (\File::exists($image_path)) {
                \File::delete($image_path);
            }

            $path = Utility::upload_file($request, 'attachment', $fileNameToStore, $dir, []);

            if ($path['flag'] == 1) {
                $policy->attachment = $fileNameToStore;
            } else {
                return $this->error($path['msg'], 400);
            }
        }

        $policy->save();

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());
        $policyNotificationArr = [
            'company_policy_name' => $request->title,
            'branch_name' => $branch->name,
        ];

        // Slack Notification
        if (isset($setting['policy_notification']) && $setting['policy_notification'] == 1) {
            Utility::send_slack_msg('new_company_policy', $policyNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_policy_notification']) && $setting['telegram_policy_notification'] == 1) {
            Utility::send_telegram_msg('new_company_policy', $policyNotificationArr);
        }

        // Webhook
        $module = 'New Company Policy';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($policy);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        return $this->success(['policy' => $policy], 'Policy created successfully.');
    }

    public function getPolicy($id)
    {
        $policy = CompanyPolicy::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with('branches')
            ->first();

        if (!$policy) {
            return $this->error('Policy not found.', 404);
        }

        return $this->success(['policy' => $policy], 'Policy detail.');
    }

    public function updatePolicy(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required|exists:branches,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,zip|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $policy = CompanyPolicy::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$policy) {
            return $this->error('Policy not found.', 404);
        }

        // Check if branch belongs to user's company
        $branch = Branch::where('id', $request->branch)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$branch) {
            return $this->error('Branch not found or access denied.', 404);
        }

        $policy->branch = $request->branch;
        $policy->title = $request->title;
        $policy->description = $request->description;

        // Handle file upload
        if ($request->hasFile('attachment')) {
            $filenameWithExt = $request->file('attachment')->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $request->file('attachment')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $dir = 'uploads/companyPolicy/';
            $image_path = $dir . $fileNameToStore;

            // Delete existing file if exists
            if (\File::exists($image_path)) {
                \File::delete($image_path);
            }

            $path = Utility::upload_file($request, 'attachment', $fileNameToStore, $dir, []);

            if ($path['flag'] == 1) {
                $policy->attachment = $fileNameToStore;
            } else {
                return $this->error($path['msg'], 400);
            }
        }

        $policy->save();

        return $this->success(['policy' => $policy], 'Policy updated successfully.');
    }

    public function deletePolicy($id)
    {
        $policy = CompanyPolicy::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$policy) {
            return $this->error('Policy not found.', 404);
        }

        // Delete attachment file if exists
        if (!empty($policy->attachment)) {
            $dir = storage_path('uploads/companyPolicy/');
            if (file_exists($dir . $policy->attachment)) {
                unlink($dir . $policy->attachment);
            }
        }

        $policy->delete();

        return $this->success([], 'Policy deleted successfully.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function downloadAttachment($id)
    {
        $policy = CompanyPolicy::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$policy) {
            return $this->error('Policy not found.', 404);
        }

        if (empty($policy->attachment)) {
            return $this->error('No attachment found.', 404);
        }

        $filePath = storage_path('uploads/companyPolicy/' . $policy->attachment);

        if (!file_exists($filePath)) {
            return $this->error('Attachment file not found.', 404);
        }

        return response()->download($filePath);
    }
}
