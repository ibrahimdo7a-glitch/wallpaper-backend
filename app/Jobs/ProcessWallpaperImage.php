<?php

namespace App\Jobs;

use App\Models\Wallpaper;
use App\Services\ImageService;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessWallpaperImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        protected int $wallpaperId
    ) {}

    public function handle(ImageService $imageService, StorageService $storageService): void
    {
        $wallpaper = Wallpaper::findOrFail($this->wallpaperId);

        // Get temp local path for processing
        $disk = config('filesystems.default');
        $tmpPath = tempnam(sys_get_temp_dir(), 'wp_') . '.' . pathinfo($wallpaper->original_file, PATHINFO_EXTENSION);

        try {
            // Download original to temp
            $content = Storage::disk($disk)->get($wallpaper->original_file);
            file_put_contents($tmpPath, $content);

            // Generate WebP
            $webpContent = $imageService->generateWebP($tmpPath);
            $webpPath = $storageService->storeWebP($webpContent);

            // Generate Thumbnail
            $thumbContent = $imageService->generateThumbnail($tmpPath);
            $thumbPath = $storageService->storeThumbnail($thumbContent);

            // Update wallpaper
            $wallpaper->update([
                'webp_file' => $webpPath,
                'thumbnail_file' => $thumbPath,
            ]);

            // Dispatch watermark job if needed
            if ($wallpaper->watermark_id) {
                ApplyWatermark::dispatch($wallpaper->id)->onQueue('watermark');
            } elseif ($wallpaper->uploader->auto_publish) {
                $wallpaper->publish($wallpaper->uploader);
            }

        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Failed to process wallpaper {$this->wallpaperId}: " . $exception->getMessage());
    }
}
