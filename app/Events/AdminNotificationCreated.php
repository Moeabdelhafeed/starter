<?php

namespace App\Events;

use App\Models\AdminNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AdminNotification $notification) {}

    public function broadcastOn(): array
    {
        // Per-type channel so subscribers see only notifications they have
        // permission for. Channel auth gate in routes/channels.php enforces it.
        return [new PrivateChannel('admin.notifications.'.$this->notification->type)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return ['notification' => $this->notification->refresh()->toArray()];
    }
}
