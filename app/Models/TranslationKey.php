<?php

namespace App\Models;

use App\Helpers\Trans;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranslationKey extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saved(fn () => Trans::clearCache());
        static::deleted(fn () => Trans::clearCache());
    }

    protected $fillable = [
        'key',
        'group',
        'sub_group',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(TranslationValue::class);
    }

    public function scopeApi(Builder $query): Builder
    {
        return $query->where('group', 'api');
    }

    public function scopeAdmin(Builder $query): Builder
    {
        return $query->where('group', 'admin');
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('group', 'custom');
    }

    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        if ($group && $group !== 'all') {
            return $query->where('group', $group);
        }

        return $query;
    }

    public function scopeSubGroup(Builder $query, ?string $subGroup): Builder
    {
        if ($subGroup && $subGroup !== 'all') {
            return $query->where('sub_group', $subGroup);
        }

        return $query;
    }
}
