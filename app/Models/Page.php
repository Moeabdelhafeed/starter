<?php

namespace App\Models;

use App\Traits\HasImage;
use App\Traits\HasTranslations;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasImage, HasTranslations, LogsActivity;

    protected $fillable = [
        'slug',
        'is_active',
    ];

    protected $translatable = [
        'name',
        'content',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
