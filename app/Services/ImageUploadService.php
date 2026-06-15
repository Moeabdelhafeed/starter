<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Centralised file upload. Raster images (jpg/jpeg/png/gif) are converted to
 * WebP at quality 80 and stored with a `.webp` extension. Vector files (svg)
 * and any non-image asset (mp4/mov/webm/etc.) pass through unmodified.
 */
class ImageUploadService
{
    /**
     * Store an uploaded file. Returns the stored path (relative to the disk).
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! $this->canConvertToWebp($extension)) {
            return $file->store($directory, $disk);
        }

        $webpBinary = $this->encodeToWebp($file->getRealPath(), $extension);
        if (! $webpBinary) {
            return $file->store($directory, $disk);
        }

        $filename = bin2hex(random_bytes(20)).'.webp';
        $path = trim($directory, '/').'/'.$filename;

        Storage::disk($disk)->put($path, $webpBinary);

        return $path;
    }

    public function delete(?string $path, string $disk = 'public'): void
    {
        if (! $path) {
            return;
        }

        $storage = Storage::disk($disk);
        if ($storage->exists($path)) {
            $storage->delete($path);
        }
    }

    /**
     * Convert an existing stored raster image to WebP in place. Returns the
     * new path on success (caller updates DB + deletes original).
     */
    public function convertExisting(string $path, string $disk = 'public'): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! $this->canConvertToWebp($extension)) {
            return null;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $webpPath = $this->webpPath($path);
        if ($webpPath === $path) {
            return $path;
        }

        if ($storage->exists($webpPath)) {
            return $webpPath;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'webp_');
        file_put_contents($tmp, $storage->get($path));

        $webpBinary = $this->encodeToWebp($tmp, $extension);
        @unlink($tmp);

        if (! $webpBinary) {
            return null;
        }

        $storage->put($webpPath, $webpBinary);

        return $webpPath;
    }

    public function canConvertToWebp(string $extension): bool
    {
        return function_exists('imagewebp')
            && in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'], true);
    }

    public function webpPath(string $path): string
    {
        $dot = strrpos($path, '.');

        return $dot === false ? $path.'.webp' : substr($path, 0, $dot).'.webp';
    }

    /**
     * @param  string  $absolutePath  local filesystem path of the raster image.
     */
    private function encodeToWebp(string $absolutePath, string $extension): ?string
    {
        $image = @imagecreatefromstring((string) file_get_contents($absolutePath));
        if (! $image) {
            return null;
        }

        if (in_array(strtolower($extension), ['png', 'gif'], true)) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        ob_start();
        $ok = imagewebp($image, null, 80);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $ok && $binary ? $binary : null;
    }
}
