<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaFile extends Model
{
    protected $fillable = [
        'url',
        'type',
        'name',
        'size',
        'fileable_id',
        'fileable_type',
    ];

    protected $appends = ['file_api'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function getFileApiAttribute(): ?string
    {
        return $this->url ? asset('storage/'.$this->url) : null;
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
