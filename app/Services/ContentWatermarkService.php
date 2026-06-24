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
     *
     * @param  string|null  $position  Per-image position override (one of the 9
     *                                 keys). Null keeps the item's stored choice,
     *                                 falling back to the preset's own position.
     */
    public function apply(ContentItem $item, Watermark $watermark, ?string $position = null): bool
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

        // Resolve the position: explicit override > item's stored choice > preset.
        if ($position) {
            $item->watermark_position = $position;
        }
        $effectivePosition = $item->watermark_position ?: $watermark->position;
        if ($effectivePosition !== $watermark->position) {
            $watermark = clone $watermark;
            $watermark->position = $effectivePosition;
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

    /**
     * Re-burn a watermark onto every item already using it — called when the
     * signature's look (position, size, opacity…) changes, so existing wallpapers
     * update without the admin re-applying them one by one.
     */
    public function reapplyAll(Watermark $watermark): int
    {
        $count = 0;
        ContentItem::where('watermark_id', $watermark->id)
            ->chunkById(50, function ($items) use ($watermark, &$count) {
                foreach ($items as $item) {
                    try {
                        if ($this->apply($item, $watermark)) {
                            $count++;
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        return $count;
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
