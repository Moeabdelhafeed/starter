<?php

namespace App\Models;

use App\Traits\HasTranslations;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory, HasTranslations, LogsActivity;

    protected $fillable = [
        'slug',
        'system_type',
        'topic',
        'trigger_model',
        'trigger_event',
        'is_active',
        'last_sent_at',
    ];

    protected $translatable = ['title', 'body'];

    public const TRIGGER_EVENTS = ['created', 'updated', 'deleted'];

    /**
     * A system template is one application code sends by looking it up, rather than one
     * an admin created. Its copy is editable and it can be switched off, but it can
     * never be deleted and its wiring (slug, trigger) is frozen: the code names it.
     *
     * Registered in `booting()`, not `booted()`, so it is the FIRST `deleting` listener —
     * HasTranslations registers one too, and a guard that ran after it would cancel the
     * delete once the template's copy was already gone.
     */
    protected static function booting(): void
    {
        // Returns false to halt, and *nothing* otherwise: any non-null return stops the
        // rest of the chain, which would leave HasTranslations' cleanup unrun.
        static::deleting(function (self $template): ?bool {
            return $template->isSystem() ? false : null;
        });
    }

    public function isSystem(): bool
    {
        return $this->system_type !== null;
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNotNull('system_type');
    }

    /**
     * The template behind one automatic notification, or null on an install whose
     * seeder has not run. A caller must handle null rather than assume the row.
     */
    public static function forSystemType(string $type): ?self
    {
        return static::where('system_type', $type)->first();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForModelEvent(Builder $query, string $modelClass, string $event): Builder
    {
        return $query
            ->where('trigger_model', $modelClass)
            ->where('trigger_event', $event)
            ->where('is_active', true);
    }
}
