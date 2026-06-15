<?php

namespace App\Traits;

use App\Jobs\SendNotificationTemplate;
use App\Models\NotificationTemplate;

/**
 * Mark a model as a notification trigger source. On `created`/`updated`/
 * `deleted` Eloquent events, scans `notification_templates` for active
 * matches (`trigger_model = static::class, trigger_event = <event>`) and
 * dispatches `SendNotificationTemplate` for each.
 *
 * Apply with:
 *     use IsNotificationTrigger;
 */
trait IsNotificationTrigger
{
    protected static function bootIsNotificationTrigger(): void
    {
        foreach (NotificationTemplate::TRIGGER_EVENTS as $event) {
            static::$event(function ($model) use ($event) {
                NotificationTemplate::forModelEvent(static::class, $event)
                    ->get()
                    ->each(fn ($template) => SendNotificationTemplate::dispatch($template->id));
            });
        }
    }
}
