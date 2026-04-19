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
        $notifications = AdminNotification::latest()
            ->take(10)
            ->get();

        $unreadCount = AdminNotification::unread()->count();

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
        $notification->markAsRead();

        return back()->with('success', __('admin.notification_marked_read'));
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        AdminNotification::unread()->update(['read_at' => now()]);

        return back()->with('success', __('admin.all_notifications_marked_read'));
    }
}
