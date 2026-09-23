<?php

namespace App\Models;

use App\Helpers\ProtectedPages;
use App\Traits\HasImage;
use App\Traits\HasTranslations;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory, HasImage, HasTranslations, LogsActivity;

    protected $fillable = [
        'slug',
        'is_active',
    ];

    /**
     * Sent to the CMS so a protected page can show a lock and hide its delete button.
     */
    protected $appends = ['is_protected'];

    protected $translatable = [
        'name',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Last line of defence for `PROTECTED_PAGES`. The controllers report a refusal
     * properly; these stop every other path — a bulk loop, a seeder, tinker — from
     * taking down a page a shipped mobile build links to.
     *
     * Deleting and deactivating are guarded together because they are the same failure:
     * `Api\Page\PageController::show()` filters on `active()`, so an inactive page
     * answers 404 exactly like a missing one, and the app's Terms screen is blank either
     * way. Blocking only the delete would leave the one-click version of the accident.
     *
     * Registered in `booting()`, not `booted()`, so the delete guard is the FIRST
     * `deleting` listener: HasTranslations registers one too, and a guard that ran after
     * it would cancel the delete only once the page's translations were already gone.
     */
    protected static function booting(): void
    {
        // Returns false to halt, and *nothing* otherwise: any non-null return stops the
        // rest of the chain, which would leave HasTranslations' cleanup unrun.
        static::deleting(function (self $page): ?bool {
            return ProtectedPages::has((string) $page->slug) ? false : null;
        });

        // Corrected rather than refused: a save that also carries new copy should still
        // land. `bulkUpdate()` is a query-builder mass update and fires no model events,
        // so it carries its own check — this does not cover it.
        static::saving(function (self $page): void {
            if (ProtectedPages::has((string) $page->slug)) {
                $page->is_active = true;
            }
        });
    }

    protected function isProtected(): Attribute
    {
        return Attribute::get(fn (): bool => ProtectedPages::has((string) $this->slug));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
