<?php

namespace App\Models;

use App\Traits\HasImage;
use App\Traits\HasTranslations;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasImage, HasTranslations, LogsActivity;

    /** Block types shown on the App Settings page. */
    public const TYPES = ['social', 'contact', 'app_store', 'google_play', 'app_gallery'];

    protected $fillable = ['type', 'url', 'is_active', 'sort_order'];

    /** Translatable label shown to app users. */
    protected $translatable = ['text'];

    /** A row is "complete" only when its text is translated in every active locale. */
    protected array $translationRequired = ['text'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
