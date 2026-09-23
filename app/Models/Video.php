<?php

namespace App\Models;

use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Video extends Model
{
    use HasImage;

    protected $fillable = [
        'url',
        'type',
        'videoable_id',
        'videoable_type',
    ];

    protected $appends = ['video_api'];

    public function getVideoApiAttribute(): ?string
    {
        return $this->url ? asset('storage/'.$this->url) : null;
    }

    /**
     * Canonical public shape for a video — same layout as Image::toApiArray(), plus the
     * optional thumbnail, which is an Image morph and so is serialized as a full image.
     *
     * @return array{id: ?int, url: ?string, type: ?string, video_api: ?string, thumbnail: ?array}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'type' => $this->type,
            'video_api' => $this->video_api,
            'thumbnail' => $this->image?->toApiArray(),
        ];
    }

    public function videoable(): MorphTo
    {
        return $this->morphTo();
    }
}
