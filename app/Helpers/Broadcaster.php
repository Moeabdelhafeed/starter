<?php

namespace App\Helpers;

class Broadcaster
{
    /**
     * Broadcast an event only when broadcasting is actually usable, and never
     * let a broadcasting failure bubble up into the originating request.
     *
     * Use this for automatic/side-effect broadcasts (admin notifications,
     * device-revoked kicks, etc.) so the app works with or without Pusher.
     */
    public static function safe(object $event): void
    {
        if (! self::isConfigured()) {
            return;
        }

        try {
            broadcast($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Whether the active broadcast driver is configured enough to attempt a send.
     * `null`/empty driver → off. Pusher → requires app_id + key + secret.
     * Other drivers (log, reverb, ably, redis) are assumed ready.
     */
    public static function isConfigured(): bool
    {
        $driver = config('broadcasting.default');

        if (empty($driver) || $driver === 'null') {
            return false;
        }

        if ($driver === 'pusher') {
            return ! empty(config('broadcasting.connections.pusher.app_id'))
                && ! empty(config('broadcasting.connections.pusher.key'))
                && ! empty(config('broadcasting.connections.pusher.secret'));
        }

        return true;
    }
}
