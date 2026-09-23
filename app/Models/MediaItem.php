<?php

namespace App\Models;

use App\Traits\HasFile;
use App\Traits\HasImage;
use App\Traits\HasVideo;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class MediaItem extends Model
{
    use HasFactory, HasFile, HasImage, HasVideo, LogsActivity;

    /**
     * Extensions accepted for the generic `file` kind. Anything executable or
     * renderable as HTML (php, html, svg, js…) is deliberately excluded because
     * the public disk serves uploads from the app's own origin.
     *
     * @var array<int, string>
     */
    public const ALLOWED_FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip', 'json'];

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
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:'.(int) config('dynamic-storage.max_image_kb')],
            'video' => ['required', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:'.(int) config('dynamic-storage.max_video_kb')],
            default => ['required', 'file', 'mimes:'.implode(',', self::ALLOWED_FILE_EXTENSIONS), 'max:'.(int) config('dynamic-storage.max_file_kb')],
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
     * (even of a different type), and persist the resolved type. `$thumbnail` is only
     * used for a video — it becomes the video's Image morph, so it carries a blurhash
     * like every other image.
     */
    public function saveMedia(UploadedFile $file, string $type, ?UploadedFile $thumbnail = null): void
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
            'video' => $this->saveVideo($file, $folder, $thumbnail),
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
     * Public/serialized shape: the type plus the matching morph, serialized by its own
     * model (Image/Video/MediaFile::toApiArray()) so every asset in the API looks the
     * same wherever it comes from. Nested rather than flattened — the morph carries its
     * own `id`/`type`, which would collide with the item's.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return match ($this->type) {
            'image' => ['type' => 'image', 'image' => $this->image?->toApiArray()],
            'video' => ['type' => 'video', 'video' => $this->video?->toApiArray()],
            default => ['type' => 'file', 'file' => $this->file?->toApiArray()],
        };
    }
}
