<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Traits\IsNotificationTrigger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationModelRegistry
{
    /**
     * Scan app/Models/ for classes using IsNotificationTrigger. Cached 5 min.
     *
     * @return array<int, array{class: string, label: string, events: array<int, string>}>
     */
    public static function all(): array
    {
        return Cache::remember('notification.trigger.models', 300, function () {
            $list = [];
            foreach (glob(app_path('Models/*.php')) as $file) {
                $class = 'App\\Models\\'.basename($file, '.php');
                if (! class_exists($class)) {
                    continue;
                }
                $uses = class_uses_recursive($class);
                if (! in_array(IsNotificationTrigger::class, $uses, true)) {
                    continue;
                }
                $list[] = [
                    'class' => $class,
                    'label' => Str::headline(class_basename($class)),
                    'events' => NotificationTemplate::TRIGGER_EVENTS,
                ];
            }

            return $list;
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('notification.trigger.models');
    }
}
