<?php

namespace App\Traits;

use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasFile
{
    public function file(): MorphOne
    {
        return $this->morphOne(MediaFile::class, 'fileable');
    }

    public function saveFile(UploadedFile $file, string $folder): MediaFile
    {
        $this->deleteFile();

        return $this->file()->create([
            'url' => $file->store($folder, 'public'),
            'type' => $file->getClientOriginalExtension(),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    public function deleteFile(): void
    {
        if ($this->file) {
            Storage::disk('public')->delete($this->file->url);
            $this->file->delete();
        }
    }
}
