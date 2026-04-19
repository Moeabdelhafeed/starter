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

    public function getVideoApiAttribute()
    {
        return $this->url ? asset('storage/'.$this->url) : null;
    }

    public function videoable(): MorphTo
    {
        return $this->morphTo();
    }
}
