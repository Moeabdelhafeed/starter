<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasTranslations, LogsActivity;

    protected $fillable = [
        'slug',
        'topic',
        'trigger_model',
        'trigger_event',
        'is_active',
        'last_sent_at',
    ];

    protected $translatable = ['title', 'body'];

    public const TRIGGER_EVENTS = ['created', 'updated', 'deleted'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModelEvent($query, string $modelClass, string $event)
    {
        return $query
            ->where('trigger_model', $modelClass)
            ->where('trigger_event', $event)
            ->where('is_active', true);
    }
}
