<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    protected $appends = ['title', 'message', 'target'];

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
     * Resolve the admin route the notification points at, plus a highlight id
     * the target page can use to glow the related row.
     *
     * Returns null when the type is not in `config/admin_notifications.php`.
     */
    /**
     * @return array{route: string, highlight: ?int}|null
     */
    public function getTargetAttribute(): ?array
    {
        $routeName = config('admin_notifications.routes.'.$this->type);

        if (! $routeName) {
            return null;
        }

        return [
            'route' => $routeName,
            'highlight' => $this->notifiable_id,
        ];
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to notifications whose `type` the given user has permission for.
     * Convention: notification type === Spatie permission name (e.g. `app_users`).
     * Pass a Spatie HasRoles user. Without permissions ⇒ empty result set.
     */
    public function scopeForUser(Builder $query, mixed $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $permissions = $user->getAllPermissions()->pluck('name')->all();

        if (empty($permissions)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('type', $permissions);
    }

    /**
     * Scope a query to only include read notifications.
     */
    public function scopeRead(Builder $query): Builder
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
}
