<?php

namespace App\Models;

use App\Traits\HasFile;
use App\Traits\HasImage;
use App\Traits\HasVideo;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaItem extends Model
{
    use HasFile, HasImage, HasVideo, LogsActivity;

    protected $fillable = [
        'key',
        'group',
        'sub_group',
        'type',
    ];

    public function scopeGroup(Builder $query, ?string $group): Builder
    {
        if ($group && $group !== 'all') {
            return $query->where('group', $group);
        }

        return $query;
    }

    public function scopeSubGroup(Builder $query, ?string $subGroup): Builder
    {
        if ($subGroup && $subGroup !== 'all') {
            return $query->where('sub_group', $subGroup);
        }

        return $query;
    }

    /**
     * Validation rules for an uploaded file, per inferred type. Keyed under `file`.
     *
     * @return array<int, string>
     */
    public static function fileRules(string $type): array
    {
        return match ($type) {
            'image' => ['required', 'image', 'max:'.(int) config('dynamic-storage.max_image_kb')],
            'video' => ['required', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:'.(int) config('dynamic-storage.max_video_kb')],
            default => ['required', 'file', 'max:'.(int) config('dynamic-storage.max_file_kb')],
        };
    }

    /**
     * Infer the media type from an uploaded file's mime.
     */
    public static function detectType(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'file',
        };
    }

    /**
     * Store the uploaded file on the matching morph, clearing any previous asset
     * (even of a different type), and persist the resolved type.
     */
    public function saveMedia(UploadedFile $file, string $type): void
    {
        // Persist the row first so it has an id — the morph FK (imageable_id/…) needs it.
        $this->type = $type;
        $this->save();

        $folder = "dynamic-media/{$this->group}/".($this->sub_group !== '' ? $this->sub_group : 'general');

        // Clear whatever was attached before (type may be changing).
        $this->deleteImage();
        $this->deleteVideo();
        $this->deleteFile();

        match ($type) {
            'image' => $this->saveImage($file, $folder),
            'video' => $this->saveVideo($file, $folder),
            default => $this->saveFile($file, $folder),
        };
    }

    /**
     * Delete every attached morph. Use before deleting the row.
     */
    public function deleteMedia(): void
    {
        $this->deleteImage();
        $this->deleteVideo();
        $this->deleteFile();
    }

    /**
     * Public/serialized shape: URL + type-specific metadata.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return match ($this->type) {
            'image' => [
                'type' => 'image',
                'url' => $this->image?->image_api,
                'blurhash' => $this->image?->blurhash,
            ],
            'video' => [
                'type' => 'video',
                'url' => $this->video?->video_api,
            ],
            default => [
                'type' => 'file',
                'url' => $this->file?->file_api,
                'name' => $this->file?->name,
                'size' => $this->file?->size,
            ],
        };
    }
}
