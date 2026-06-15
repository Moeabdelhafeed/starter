<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use kornrunner\Blurhash\Blurhash;

class Image extends Model
{
    protected $fillable = [
        'url',
        'type',
        'blurhash',
        'imageable_id',
        'imageable_type',
    ];

    protected $appends = ['image_api'];

    protected static function booted(): void
    {
        static::creating(function (Image $image) {
            if ($image->url && ! $image->blurhash) {
                $image->blurhash = static::generateBlurhash($image->url);
            }
        });
    }

    public static function generateBlurhash(string $url): ?string
    {
        $path = Storage::disk('public')->path($url);

        if (! file_exists($path)) {
            return null;
        }

        $image = imagecreatefromstring(file_get_contents($path));

        if (! $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $maxDimension = 64;
        if ($width > $maxDimension || $height > $maxDimension) {
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = (int) ($width * $ratio);
            $newHeight = (int) ($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $index = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $index);
                $row[] = [$colors['red'], $colors['green'], $colors['blue']];
            }
            $pixels[] = $row;
        }

        imagedestroy($image);

        return Blurhash::encode($pixels, 4, 3);
    }

    public function getImageApiAttribute()
    {
        return $this->url ? asset('storage/'.$this->url) : null;
    }

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
