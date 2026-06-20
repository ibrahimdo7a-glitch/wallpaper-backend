<?php

namespace App\Jobs;

use App\Models\Wallpaper;
use App\Services\StorageService;
use App\Services\WatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyWatermark implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        protected int $wallpaperId
    ) {}

    public function handle(WatermarkService $watermarkService, StorageService $storageService): void
    {
        $wallpaper = Wallpaper::with('watermark')->findOrFail($this->wallpaperId);

        if (! $wallpaper->watermark) {
            return;
        }

        // Apply to original (JPG output)
        $watermarkedContent = $watermarkService->apply($wallpaper->original_file, $wallpaper->watermark);
        $watermarkedPath = $storageService->storeWatermarked($watermarkedContent, 'jpg');

        // Apply to WebP (if exists)
        $watermarkedWebpPath = null;
        if ($wallpaper->webp_file) {
            $watermarkedWebpContent = $watermarkService->apply($wallpaper->webp_file, $wallpaper->watermark);
            $watermarkedWebpPath = $storageService->storeWatermarked($watermarkedWebpContent, 'webp');
        }

        $updateData = [
            'watermarked_file' => $watermarkedPath,
            'watermark_applied' => true,
        ];

        if ($watermarkedWebpPath) {
            $updateData['watermarked_webp_file'] = $watermarkedWebpPath;
        }

        $wallpaper->update($updateData);

        // Auto-publish if user has permission
        if ($wallpaper->uploader->auto_publish) {
            $wallpaper->publish($wallpaper->uploader);
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Failed to apply watermark to wallpaper {$this->wallpaperId}: " . $exception->getMessage());
    }
}
