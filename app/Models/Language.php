<?php

namespace App\Models;

use App\Helpers\Trans;
use App\Traits\HasImage;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    use HasFactory, HasImage, LogsActivity;

    private const CACHE_DEFAULT = 'languages.default_code';

    private const CACHE_ACTIVE = 'languages.active_codes';

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    /**
     * Drop the cached language lookups and every cached translation.
     *
     * Model events cover ordinary saves, but a query-builder mass update
     * (`Language::where(...)->update(...)`, used when moving the default flag)
     * fires no events — those callers must invoke this directly or the cached
     * default code stays wrong for the life of the cache.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_DEFAULT);
        Cache::forget(self::CACHE_ACTIVE);
        Trans::clearCache();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public static function getDefault(): ?self
    {
        return static::default()->first();
    }

    /**
     * Code of the default language, cached. Falls back to the app locale.
     */
    public static function defaultCode(): string
    {
        return Cache::rememberForever(self::CACHE_DEFAULT, fn (): string => static::getDefault()?->code ?? config('app.fallback_locale', 'en'));
    }

    /**
     * Codes of every active language, cached.
     *
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        return Cache::rememberForever(self::CACHE_ACTIVE, fn (): array => static::active()->orderBy('id')->pluck('code')->all());
    }

    public function translationValues(): HasMany
    {
        return $this->hasMany(TranslationValue::class, 'locale', 'code');
    }
}
