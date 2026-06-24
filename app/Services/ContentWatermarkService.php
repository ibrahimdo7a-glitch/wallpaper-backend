<?php

namespace App\Services;

use App\Models\ContentItem;
use App\Models\Watermark;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Burns a watermark into a ContentItem's image while keeping the clean original,
 * so the watermark can be changed or removed later without quality loss.
 */
class ContentWatermarkService
{
    public function __construct(protected WatermarkService $watermarks) {}

    private const DIR = 'content-items/watermarked';

    /**
     * Apply (or re-apply) a watermark. Re-applying a different watermark always
     * starts from the clean original, never from an already-watermarked image.
     */
    public function apply(ContentItem $item, Watermark $watermark): bool
    {
        $disk     = config('filesystems.default', 'public');
        $previous = $item->image_path;
        $wasWatermarked = $previous && str_contains($previous, self::DIR);

        // A non-watermarked current image is, by definition, the clean original
        // (covers first upload, retroactive apply, and replacing the image later).
        if (! $wasWatermarked && $previous) {
            $item->original_image_path = $previous;
        }

        $source = $item->original_image_path ?: $previous;
        if (! $source || ! Storage::disk($disk)->exists($source)) {
            return false;
        }

        $content    = $this->watermarks->apply($source, $watermark);
        $path       = self::DIR . '/' . Str::ulid() . '.jpg';
        $visibility = in_array($disk, ['r2', 's3']) ? 'private' : 'public';
        Storage::disk($disk)->put($path, $content, $visibility);

        $item->image_path     = $path;
        $item->thumbnail_path = $path;
        $item->file_path      = $path;
        $item->watermark_id   = $watermark->id;
        $item->saveQuietly();

        // Drop the superseded watermarked file (never the clean original).
        if ($wasWatermarked && $previous !== $item->original_image_path) {
            Storage::disk($disk)->delete($previous);
        }

        return true;
    }

    /** Revert to the clean original and drop the watermark. */
    public function remove(ContentItem $item): bool
    {
        $disk     = config('filesystems.default', 'public');
        $previous = $item->image_path;

        if (empty($item->original_image_path)) {
            // Never watermarked — just clear the reference.
            if ($item->watermark_id) {
                $item->watermark_id = null;
                $item->saveQuietly();
            }
            return false;
        }

        $orig = $item->original_image_path;
        $item->image_path     = $orig;
        $item->thumbnail_path = $orig;
        $item->file_path      = $orig;
        $item->watermark_id   = null;
        $item->saveQuietly();

        if ($previous && $previous !== $orig && str_contains($previous, self::DIR)) {
            Storage::disk($disk)->delete($previous);
        }

        return true;
    }
}
