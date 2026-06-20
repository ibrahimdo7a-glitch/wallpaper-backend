<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'r2');
    }

    public function storeOriginal(UploadedFile $file): string
    {
        $path = 'wallpapers/original/' . date('Y/m');
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();

        Storage::disk($this->disk)->putFileAs($path, $file, $filename, 'private');

        return "{$path}/{$filename}";
    }

    public function storeProcessed(string $content, string $subdir, string $extension = 'webp'): string
    {
        $path = "wallpapers/{$subdir}/" . date('Y/m');
        $filename = Str::random(40) . ".{$extension}";
        $fullPath = "{$path}/{$filename}";

        Storage::disk($this->disk)->put($fullPath, $content, 'public');

        return $fullPath;
    }

    public function storeThumbnail(string $content): string
    {
        return $this->storeProcessed($content, 'thumbnails', 'webp');
    }

    public function storeWebP(string $content): string
    {
        return $this->storeProcessed($content, 'webp', 'webp');
    }

    public function storeWatermarked(string $content, string $extension = 'jpg'): string
    {
        return $this->storeProcessed($content, 'watermarked', $extension);
    }

    public function storeWatermarkImage(UploadedFile $file): string
    {
        $path = 'watermarks';
        $filename = Str::random(30) . '.' . $file->getClientOriginalExtension();

        Storage::disk($this->disk)->putFileAs($path, $file, $filename, 'public');

        return "{$path}/{$filename}";
    }

    public function storeCategoryImage(UploadedFile $file): string
    {
        $path = 'categories';
        $filename = Str::random(30) . '.' . $file->getClientOriginalExtension();

        Storage::disk($this->disk)->putFileAs($path, $file, $filename, 'public');

        return "{$path}/{$filename}";
    }

    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    public function getTemporaryUrl(string $path, int $minutes = 5): string
    {
        return Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }

    public function delete(string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Storage::disk($this->disk)->delete($path);
    }

    public function deleteWallpaperFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            $this->delete($path);
        }
    }
}
