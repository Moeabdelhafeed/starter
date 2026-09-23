<?php

namespace App\Traits;

use App\Models\Video;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasVideo
{
    public function video(): MorphOne
    {
        return $this->morphOne(Video::class, 'videoable');
    }

    public function saveVideo(UploadedFile $file, string $folder, ?UploadedFile $thumbnail = null): Video
    {
        $this->deleteVideo();

        $video = $this->video()->create([
            'url' => $file->store($folder, 'public'),
            'type' => $file->getClientOriginalExtension(),
        ]);

        if ($thumbnail) {
            $video->saveImage($thumbnail, $folder.'/thumbnails');
        }

        return $video;
    }

    public function deleteVideo(): void
    {
        if ($this->video) {
            $this->video->deleteImage();
            Storage::disk('public')->delete($this->video->url);
            $this->video->delete();
        }
    }
}
