<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;

trait HasImage
{
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function saveImage(UploadedFile $file, string $folder): Image
    {
        $this->deleteImage();

        $uploader = app(ImageUploadService::class);
        $path = $uploader->store($file, $folder);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->image()->create([
            'url' => $path,
            'type' => $extension,
        ]);
    }

    public function deleteImage(): void
    {
        if ($this->image) {
            app(ImageUploadService::class)->delete($this->image->url);
            $this->image->delete();
        }
    }
}
