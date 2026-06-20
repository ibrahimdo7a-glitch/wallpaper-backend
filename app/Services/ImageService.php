<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function validateFile(UploadedFile $file): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSizeMb = config('app.max_upload_size_mb', 20);

        if (! in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('نوع الملف غير مسموح به. يُسمح فقط بـ JPG, PNG, WebP');
        }

        if ($file->getSize() > ($maxSizeMb * 1024 * 1024)) {
            throw new \InvalidArgumentException("حجم الملف يتجاوز الحد المسموح به ({$maxSizeMb} MB)");
        }
    }

    public function getImageInfo(UploadedFile $file): array
    {
        $image = $this->manager->read($file->getPathname());

        return [
            'width' => $image->width(),
            'height' => $image->height(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'image_hash' => hash_file('sha256', $file->getPathname()),
            'resolution_label' => $this->getResolutionLabel($image->width(), $image->height()),
        ];
    }

    public function generateWebP(string $sourcePath, int $quality = 85): string
    {
        $image = $this->manager->read($sourcePath);

        return $image->toWebp($quality)->toString();
    }

    public function generateThumbnail(string $sourcePath, int $width = 400, int $height = 250): string
    {
        $image = $this->manager->read($sourcePath);
        $image->cover($width, $height);

        return $image->toWebp(80)->toString();
    }

    public function generateFromContent(string $content): string
    {
        return $this->manager->read($content)->toWebp(85)->toString();
    }

    public function getResolutionLabel(int $width, int $height): string
    {
        $mp = ($width * $height) / 1_000_000;

        return match (true) {
            $mp >= 33 => '8K',
            $mp >= 8 => '4K',
            $mp >= 3.7 => 'QHD',
            $mp >= 2 => 'FHD',
            $mp >= 0.9 => 'HD',
            default => 'SD',
        };
    }

    public function readFromStorage(string $path): \Intervention\Image\Image
    {
        $content = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->get($path);

        return $this->manager->read($content);
    }
}
