<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User-specific private channel - only the user can listen
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Per-permission AdminNotification channels. Convention: notification `type`
// matches a Spatie permission name. Subscribe is allowed only when the user
// holds that permission — Pusher won't deliver payloads to unauthorized
// admins, closing the DevTools data-leak side-channel.
Broadcast::channel('admin.notifications.{type}', function ($user, $type) {
    return $user && method_exists($user, 'can') && $user->can($type);
});
