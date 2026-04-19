<?php

namespace App\Traits;

use App\Models\Image;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasImage
{
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function saveImage(UploadedFile $file, string $folder): Image
    {
        $this->deleteImage();

        return $this->image()->create([
            'url' => $file->store($folder, 'public'),
            'type' => $file->getClientOriginalExtension(),
        ]);
    }

    public function deleteImage(): void
    {
        if ($this->image) {
            Storage::disk('public')->delete($this->image->url);
            $this->image->delete();
        }
    }
}
