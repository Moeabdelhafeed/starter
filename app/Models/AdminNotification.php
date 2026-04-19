<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminNotification extends Model
{
    protected $fillable = [
        'type',
        'title_key',
        'message_key',
        'model_key',
        'action',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected $appends = ['title', 'message'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the translated title.
     */
    public function getTitleAttribute(): string
    {
        $modelName = __($this->model_key);

        // If translation doesn't exist, extract from model_key (e.g., 'admin.model_user' -> 'User')
        if ($modelName === $this->model_key) {
            $modelName = ucfirst(str_replace('_', ' ', str_replace('admin.model_', '', $this->model_key)));
        }

        return __($this->title_key, [
            'model' => $modelName,
            'event' => $this->action,
        ]);
    }

    /**
     * Get the translated message.
     */
    public function getMessageAttribute(): ?string
    {
        if (! $this->message_key) {
            return null;
        }

        $name = $this->data['name'] ?? "#{$this->notifiable_id}";

        return __($this->message_key, ['name' => $name]);
    }

    /**
     * Get the notifiable model (the model that triggered the notification).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Check if the notification is read.
     */
    public function isRead(): bool
    {
        return ! is_null($this->read_at);
    }
}
