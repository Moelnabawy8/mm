<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationApiController extends Controller
{
    use ApiResponser;

    public function getNotifications()
    {
        $notifications = Notification::where('user_id', Auth::user()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        // Format notifications with HTML content
        $formattedNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => json_decode($notification->data),
                'is_read' => $notification->is_read,
                'created_at' => $notification->created_at,
                'updated_at' => $notification->updated_at,
                'html_content' => $notification->toHtml(),
                'created_at_human' => $notification->created_at->diffForHumans(),
            ];
        });

        $unreadCount = $notifications->where('is_read', false)->count();

        return $this->success([
            'notifications' => $formattedNotifications,
            'unread_count' => $unreadCount,
            'total_count' => $notifications->count()
        ], 'Notifications retrieved.');
    }

    public function markNotificationRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::user()->id())
            ->first();

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->is_read = true;
        $notification->save();

        return $this->success(['notification' => $notification], 'Notification marked as read.');
    }

    public function markAllNotificationsRead()
    {
        $notifications = Notification::where('user_id', Auth::user()->id())
            ->where('is_read', false)
            ->get();

        foreach ($notifications as $notification) {
            $notification->is_read = true;
            $notification->save();
        }

        return $this->success(['marked_count' => $notifications->count()], 'All notifications marked as read.');
    }

    public function deleteNotification($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::user()->id())
            ->first();

        if (!$notification) {
            return $this->error('Notification not found.', 404);
        }

        $notification->delete();

        return $this->success([], 'Notification deleted successfully.');
    }

    public function deleteAllReadNotifications()
    {
        $notifications = Notification::where('user_id', Auth::user()->id())
            ->where('is_read', true)
            ->get();

        foreach ($notifications as $notification) {
            $notification->delete();
        }

        return $this->success(['deleted_count' => $notifications->count()], 'All read notifications deleted successfully.');
    }

    public function getUnreadNotifications()
    {
        $notifications = Notification::where('user_id', Auth::user()->id())
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();

        // Format notifications with HTML content
        $formattedNotifications = $notifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => json_decode($notification->data),
                'created_at' => $notification->created_at,
                'html_content' => $notification->toHtml(),
                'created_at_human' => $notification->created_at->diffForHumans(),
            ];
        });

        return $this->success([
            'notifications' => $formattedNotifications,
            'unread_count' => $notifications->count()
        ], 'Unread notifications retrieved.');
    }

    public function getNotificationStats()
    {
        $totalNotifications = Notification::where('user_id', Auth::user()->id())->count();
        $unreadNotifications = Notification::where('user_id', Auth::user()->id())
            ->where('is_read', false)
            ->count();
        $readNotifications = $totalNotifications - $unreadNotifications;

        // Get notification types distribution
        $typeDistribution = Notification::where('user_id', Auth::user()->id())
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        return $this->success([
            'total_notifications' => $totalNotifications,
            'unread_notifications' => $unreadNotifications,
            'read_notifications' => $readNotifications,
            'type_distribution' => $typeDistribution
        ], 'Notification statistics retrieved.');
    }

    public function createNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if target user belongs to the same company
        $targetUser = User::find($request->user_id);
        if (!$targetUser || $targetUser->created_by != Auth::user()->creatorId()) {
            return $this->error('Target user not found or access denied.', 404);
        }

        $notification = new Notification();
        $notification->user_id = $request->user_id;
        $notification->type = $request->type;
        $notification->data = json_encode($request->data);
        $notification->is_read = false;
        $notification->save();

        return $this->success(['notification' => $notification], 'Notification created successfully.');
    }
}
