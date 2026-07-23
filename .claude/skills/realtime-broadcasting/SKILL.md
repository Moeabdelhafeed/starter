---
name: realtime-broadcasting
description: "Use whenever adding or working with real-time broadcast events (Pusher) in this starter: broadcast event classes, private channel authorization (routes/channels.php), Echo listeners in Vue, or the DevSettings Pusher config. Also covers the project's mandatory QUEUE_CONNECTION=sync + ShouldBroadcastNow (never ShouldBroadcast) convention, since this starter has no queue worker. Trigger on: 'broadcast an event', 'add a Pusher channel', 'listen for a websocket event', 'private-user channel', ShouldBroadcast questions."
metadata:
  author: project
---

# Pusher Broadcasting

The app supports real-time broadcasting via Pusher for WebSocket-based features. Broadcasting is configured with Sanctum authentication for private channels.

## Setup

1. Configure Pusher credentials in DevSettings under "Pusher Broadcasting" (separate configs for local and production).
2. The frontend uses `@laravel/echo-vue` for listening to broadcasts.

## Configuration

Broadcasting is configured in `bootstrap/app.php`:
```php
->withBroadcasting(
    __DIR__.'/../routes/channels.php',
    ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
)
```

## Environment Variables

**Backend (server-side):**
- `BROADCAST_CONNECTION` — Set to `pusher` to enable Pusher broadcasting.
- `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_CLUSTER` — Pusher credentials (e.g. cluster `eu`, `us2`, `ap1`).

**Frontend (Vite):**
- `VITE_PUSHER_APP_KEY` / `VITE_PUSHER_APP_CLUSTER` — auto-set when saving config in DevSettings.

## Channel Authorization

Private channels are defined in `routes/channels.php`:
```php
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

## Creating Broadcast Events

Always use `ShouldBroadcastNow`, never `ShouldBroadcast` — this starter runs `QUEUE_CONNECTION=sync` (no queue worker), so `ShouldBroadcast` would silently never fire without a worker running:

```php
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class OrderStatusChanged implements ShouldBroadcastNow
{
    public function __construct(
        public int $userId,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'order.status';
    }

    public function broadcastWith(): array
    {
        return ['status' => $this->status];
    }
}
```

## Dispatching Events

```php
broadcast(new OrderStatusChanged($user->id, 'shipped'));
```

## Frontend Listening (Vue)

```vue
<script setup>
import { useEchoChannel } from '@laravel/echo-vue';

const channel = useEchoChannel(`private-user.${userId}`);

channel.listen('.order.status', (event) => {
    console.log('Order status:', event.status);
});
</script>
```

## DevSettings Integration

- **Local config:** Pusher credentials for development environment.
- **Production config:** Separate Pusher credentials for production (stored in `.env.production`).
- **Test broadcast:** Send a test event to verify configuration works.

## Why `QUEUE_CONNECTION=sync`

Always use `QUEUE_CONNECTION=sync` in this starter:
- Events broadcast immediately without queue workers.
- Simpler local development setup.
- No need for Redis or database queue tables.
