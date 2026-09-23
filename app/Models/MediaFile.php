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

    /**
     * Canonical public shape for a stored file, alongside Image/Video::toApiArray().
     *
     * @return array{id: ?int, url: ?string, type: ?string, name: ?string, size: ?int, file_api: ?string}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'type' => $this->type,
            'name' => $this->name,
            'size' => $this->size,
            'file_api' => $this->file_api,
        ];
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
