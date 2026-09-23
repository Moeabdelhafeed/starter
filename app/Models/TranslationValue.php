<?php

namespace App\Models;

use App\Helpers\Trans;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationValue extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saved(fn () => Trans::clearCache());
        static::deleted(fn () => Trans::clearCache());
    }

    protected $fillable = [
        'translation_key_id',
        'value',
        'locale',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }
}
