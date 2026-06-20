<?php

namespace App\Services;

use App\Models\Watermark;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Typography\FontFactory;

class WatermarkService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function apply(string $imagePath, Watermark $watermark): string
    {
        $imageContent = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->get($imagePath);
        $image = $this->manager->read($imageContent);

        return match ($watermark->type) {
            'text' => $this->applyTextWatermark($image, $watermark),
            'image' => $this->applyImageWatermark($image, $watermark),
            'combined' => $this->applyCombinedWatermark($image, $watermark),
            default => $image->toJpeg(90)->toString(),
        };
    }

    protected function applyTextWatermark($image, Watermark $watermark): string
    {
        $locale = app()->getLocale();
        $text = $locale === 'ar' ? ($watermark->text_ar ?? $watermark->text_en) : ($watermark->text_en ?? $watermark->text_ar);

        if (! $text) {
            return $image->toJpeg(90)->toString();
        }

        $position = $this->calculatePosition($image->width(), $image->height(), $watermark);
        $color = $this->hexToRgba($watermark->font_color, $watermark->opacity);

        $image->text($text, $position['x'], $position['y'], function (FontFactory $font) use ($watermark, $color) {
            $font->filename(storage_path("fonts/{$watermark->font_family}.ttf"));
            $font->size($watermark->font_size);
            $font->color($color);
            $font->align('center');
            $font->valign('middle');
        });

        return $image->toJpeg(90)->toString();
    }

    protected function applyImageWatermark($image, Watermark $watermark): string
    {
        if (! $watermark->image_file) {
            return $image->toJpeg(90)->toString();
        }

        $watermarkContent = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->get($watermark->image_file);
        $watermarkImage = $this->manager->read($watermarkContent);

        // Scale watermark
        $scale = $watermark->scale / 100;
        $newWidth = (int) ($watermarkImage->width() * $scale);
        $newHeight = (int) ($watermarkImage->height() * $scale);
        $watermarkImage->scale($newWidth, $newHeight);

        $position = $this->calculatePosition(
            $image->width(),
            $image->height(),
            $watermark,
            $watermarkImage->width(),
            $watermarkImage->height()
        );

        $image->place($watermarkImage, 'top-left', $position['x'], $position['y'], $watermark->opacity);

        return $image->toJpeg(90)->toString();
    }

    protected function applyCombinedWatermark($image, Watermark $watermark): string
    {
        // Apply image first, then text
        if ($watermark->image_file) {
            $imageContent = $this->applyImageWatermark($image, $watermark);
            $image = $this->manager->read($imageContent);
        }

        return $this->applyTextWatermark($image, $watermark);
    }

    protected function calculatePosition(
        int $imageWidth,
        int $imageHeight,
        Watermark $watermark,
        int $wmWidth = 0,
        int $wmHeight = 0
    ): array {
        $mx = $watermark->margin_x;
        $my = $watermark->margin_y;

        return match ($watermark->position) {
            'top-left' => ['x' => $mx + $wmWidth / 2, 'y' => $my + $wmHeight / 2],
            'top-center' => ['x' => $imageWidth / 2, 'y' => $my + $wmHeight / 2],
            'top-right' => ['x' => $imageWidth - $mx - $wmWidth / 2, 'y' => $my + $wmHeight / 2],
            'middle-left' => ['x' => $mx + $wmWidth / 2, 'y' => $imageHeight / 2],
            'center' => ['x' => $imageWidth / 2, 'y' => $imageHeight / 2],
            'middle-right' => ['x' => $imageWidth - $mx - $wmWidth / 2, 'y' => $imageHeight / 2],
            'bottom-left' => ['x' => $mx + $wmWidth / 2, 'y' => $imageHeight - $my - $wmHeight / 2],
            'bottom-center' => ['x' => $imageWidth / 2, 'y' => $imageHeight - $my - $wmHeight / 2],
            'bottom-right' => ['x' => $imageWidth - $mx - $wmWidth / 2, 'y' => $imageHeight - $my - $wmHeight / 2],
            default => ['x' => $imageWidth / 2, 'y' => $imageHeight / 2],
        };
    }

    protected function hexToRgba(string $hex, int $opacity): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $a = $opacity / 100;

        return "rgba({$r}, {$g}, {$b}, {$a})";
    }
}
