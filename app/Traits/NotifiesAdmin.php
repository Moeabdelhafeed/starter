<?php

namespace App\Traits;

use App\Models\AdminNotification;

trait NotifiesAdmin
{
    protected static function bootNotifiesAdmin(): void
    {
        foreach (static::getNotifyEvents() as $event) {
            static::$event(function ($model) use ($event) {
                $model->createAdminNotification($event);
            });
        }
    }

    /**
     * Get the events that should trigger admin notifications.
     * Override this in your model to customize which events trigger notifications.
     *
     * @return array<string>
     */
    protected static function getNotifyEvents(): array
    {
        if (isset(static::$notifyEvents)) {
            return static::$notifyEvents;
        }

        return ['created', 'deleted'];
    }

    /**
     * Get the notification type for this model.
     * Override this in your model to customize the type.
     */
    protected function getNotificationType(): string
    {
        if (isset(static::$notifyType)) {
            return static::$notifyType;
        }

        // Default: convert class name to snake_case plural
        // e.g., User -> users, AppUser -> app_users
        $className = class_basename($this);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)).'s';
    }

    /**
     * Get the notification title key for the given event.
     * Returns the translation key (not translated text).
     */
    protected function getNotificationTitleKey(string $event): string
    {
        return match ($event) {
            'created' => 'admin.notification_created',
            'updated' => 'admin.notification_updated',
            'deleted' => 'admin.notification_deleted',
            default => 'admin.notification_event',
        };
    }

    /**
     * Get the notification message key for the given event.
     * Returns the translation key (not translated text).
     */
    protected function getNotificationMessageKey(string $event): ?string
    {
        return match ($event) {
            'created' => 'admin.notification_created_message',
            'updated' => 'admin.notification_updated_message',
            'deleted' => 'admin.notification_deleted_message',
            default => null,
        };
    }

    /**
     * Get the model translation key for notifications.
     * Uses admin.model_{snake_case_class_name} translation key.
     */
    protected function getModelTranslationKey(): string
    {
        $className = class_basename($this);
        $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        return 'admin.model_'.$snakeName;
    }

    /**
     * Get additional data to store with the notification.
     * Override this in your model to customize the data.
     */
    protected function getNotificationData(string $event): ?array
    {
        if (method_exists($this, 'customNotificationData')) {
            return $this->customNotificationData($event);
        }

        return [
            'name' => $this->name ?? $this->title ?? null,
            'email' => $this->email ?? null,
        ];
    }

    /**
     * Create an admin notification for this model event.
     * Stores translation keys instead of translated text for proper localization.
     */
    public function createAdminNotification(string $event): void
    {
        // Skip if this notification type is disabled
        if (method_exists($this, 'shouldNotify') && ! $this->shouldNotify($event)) {
            return;
        }

        $name = $this->name ?? $this->title ?? $this->email ?? "#{$this->getKey()}";

        AdminNotification::create([
            'type' => $this->getNotificationType(),
            'title_key' => $this->getNotificationTitleKey($event),
            'message_key' => $this->getNotificationMessageKey($event),
            'model_key' => $this->getModelTranslationKey(),
            'action' => $event,
            'notifiable_type' => get_class($this),
            'notifiable_id' => $this->getKey(),
            'data' => array_merge($this->getNotificationData($event) ?? [], [
                'name' => $name,
            ]),
        ]);
    }
}
