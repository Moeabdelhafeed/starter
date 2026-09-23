<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;

class NotificationController extends Controller
{
    /**
     * Get recent notifications for the sidebar.
     */
    public function recent()
    {
        $user = auth()->user();

        $notifications = AdminNotification::forUser($user)
            ->latest()
            ->take(10)
            ->get();

        $unreadCount = AdminNotification::forUser($user)->unread()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(AdminNotification $notification)
    {
        abort_unless(auth()->user()?->can($notification->type), 403);

        $notification->markAsRead();

        return back()->with('success', __('admin.notification_marked_read'));
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        AdminNotification::forUser(auth()->user())->unread()->update(['read_at' => now()]);

        return back()->with('success', __('admin.all_notifications_marked_read'));
    }
}
