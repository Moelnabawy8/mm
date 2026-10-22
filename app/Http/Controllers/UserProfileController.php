<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRequest;
use App\Models\UserSubscription;
use App\Models\Department;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserProfileController extends Controller
{
    public function show($id)
    {
        $user = User::findOrFail($id);
        
        // Check permissions
        if (Auth::user()->type != 'super admin' && Auth::id() != $id) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $profile = UserProfile::firstOrCreate(
            ['user_id' => $id],
            ['created_by' => Auth::id()]
        );

        $requests = UserRequest::where('user_id', $id)->orderBy('created_at', 'desc')->get();
        
        // Get user subscriptions/orders
        $subscriptions = Order::where('user_id', $id)->orderBy('created_at', 'desc')->get();
        
        // Get custom subscriptions
        $customSubscriptions = UserSubscription::where('user_id', $id)->orderBy('created_at', 'desc')->get();
        
        // Get departments
        $departments = Department::where('created_by', $user->creatorId())->get();

        return view('user_profile.show', compact('user', 'profile', 'requests', 'subscriptions', 'customSubscriptions', 'departments'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        
        // Check permissions - only user or super admin can edit
        if (Auth::user()->type != 'super admin' && Auth::id() != $id) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $profile = UserProfile::firstOrCreate(
            ['user_id' => $id],
            ['created_by' => Auth::id()]
        );

        $departments = Department::where('created_by', $user->creatorId())->get();

        return view('user_profile.edit', compact('user', 'profile', 'departments'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Check permissions
        if (Auth::user()->type != 'super admin' && Auth::id() != $id) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $profile = UserProfile::where('user_id', $id)->first();
        
        if (!$profile) {
            $profile = new UserProfile();
            $profile->user_id = $id;
            $profile->created_by = Auth::id();
        }

        $profile->job_title = $request->job_title;
        $profile->primary_role = $request->primary_role;
        $profile->official_responsibilities = $request->official_responsibilities ? json_encode(array_filter($request->official_responsibilities)) : null;
        $profile->daily_tasks = $request->daily_tasks ? json_encode(array_filter($request->daily_tasks)) : null;
        $profile->periodic_tasks = $request->periodic_tasks ? json_encode(array_filter($request->periodic_tasks)) : null;
        $profile->work_details = $request->work_details;
        $profile->task_execution_method = $request->task_execution_method;
        $profile->department_connections = $request->department_connections ? json_encode($request->department_connections) : null;
        $profile->collaboration_details = $request->collaboration_details;
        
        $profile->save();

        return redirect()->route('user.profile.show', $id)->with('success', __('Profile updated successfully.'));
    }

    // User Requests Management
    public function storeRequest(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:requirement,request,tool,resource',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $userRequest = new UserRequest();
        $userRequest->user_id = $id;
        $userRequest->title = $request->title;
        $userRequest->description = $request->description;
        $userRequest->type = $request->type;
        $userRequest->priority = $request->priority;
        $userRequest->status = 'pending';
        $userRequest->created_by = Auth::id();
        $userRequest->save();

        return redirect()->back()->with('success', __('Request created successfully.'));
    }

    public function updateRequest(Request $request, $requestId)
    {
        $userRequest = UserRequest::findOrFail($requestId);
        
        // Only super admin can approve/reject
        if (Auth::user()->type == 'super admin') {
            $userRequest->status = $request->status;
            $userRequest->admin_notes = $request->admin_notes;
            if ($request->status == 'approved') {
                $userRequest->approved_at = now();
                $userRequest->approved_by = Auth::id();
            }
        } else {
            // User can only update their own pending requests
            if ($userRequest->user_id != Auth::id() || $userRequest->status != 'pending') {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
            $userRequest->title = $request->title;
            $userRequest->description = $request->description;
            $userRequest->type = $request->type;
            $userRequest->priority = $request->priority;
        }
        
        $userRequest->save();

        return redirect()->back()->with('success', __('Request updated successfully.'));
    }

    public function deleteRequest($requestId)
    {
        $userRequest = UserRequest::findOrFail($requestId);
        
        // Only user or super admin can delete
        if (Auth::user()->type != 'super admin' && $userRequest->user_id != Auth::id()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $userRequest->delete();

        return redirect()->back()->with('success', __('Request deleted successfully.'));
    }

    // User Subscriptions Management
    public function storeSubscription(Request $request, $id)
    {
        $request->validate([
            'subscription_name' => 'required|string|max:255',
            'plan_type' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,expired,cancelled,pending',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Check permissions
        if (Auth::user()->type != 'super admin' && Auth::id() != $id) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $subscription = new UserSubscription();
        $subscription->user_id = $id;
        $subscription->subscription_name = $request->subscription_name;
        $subscription->plan_type = $request->plan_type;
        $subscription->price = $request->price ?? 0;
        $subscription->status = $request->status;
        $subscription->start_date = $request->start_date;
        $subscription->end_date = $request->end_date;
        $subscription->notes = $request->notes;
        $subscription->created_by = Auth::id();
        $subscription->save();

        return redirect()->back()->with('success', __('Subscription added successfully.'));
    }

    public function updateSubscription(Request $request, $subscriptionId)
    {
        $subscription = UserSubscription::findOrFail($subscriptionId);
        
        // Check permissions
        if (Auth::user()->type != 'super admin' && $subscription->user_id != Auth::id()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $request->validate([
            'subscription_name' => 'required|string|max:255',
            'plan_type' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,expired,cancelled,pending',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $subscription->subscription_name = $request->subscription_name;
        $subscription->plan_type = $request->plan_type;
        $subscription->price = $request->price ?? 0;
        $subscription->status = $request->status;
        $subscription->start_date = $request->start_date;
        $subscription->end_date = $request->end_date;
        $subscription->notes = $request->notes;
        $subscription->save();

        return redirect()->back()->with('success', __('Subscription updated successfully.'));
    }

    public function deleteSubscription($subscriptionId)
    {
        $subscription = UserSubscription::findOrFail($subscriptionId);
        
        // Check permissions
        if (Auth::user()->type != 'super admin' && $subscription->user_id != Auth::id()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $subscription->delete();

        return redirect()->back()->with('success', __('Subscription deleted successfully.'));
    }
}
