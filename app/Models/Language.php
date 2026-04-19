<?php

namespace App\Models;

use App\Traits\HasImage;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasImage, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public static function getDefault()
    {
        return static::default()->first();
    }

    public function translationValues()
    {
        return $this->hasMany(TranslationValue::class, 'locale', 'code');
    }
}
