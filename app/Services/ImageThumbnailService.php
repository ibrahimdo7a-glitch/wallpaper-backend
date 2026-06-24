<?php

namespace App\Services;

use App\Models\ContentItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Builds a small WebP thumbnail for a content image so admin tables and public
 * grids load ~30 KB instead of the full ~500 KB original. Thumbnails are stored
 * with a long immutable cache header so browsers/CDN keep them.
 */
class ImageThumbnailService
{
    private const DIR = 'content-items/thumbs';
    private const CACHE = 'public, max-age=31536000, immutable';

    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /** Generate a thumbnail of $sourcePath; returns the stored path or null. */
    public function generate(?string $sourcePath, int $width = 500): ?string
    {
        $disk = config('filesystems.default', 'public');

        if (! $sourcePath || ! Storage::disk($disk)->exists($sourcePath)) {
            return null;
        }

        try {
            $image = $this->manager->read(Storage::disk($disk)->get($sourcePath));
            $image->scaleDown(width: $width);
            $content = $image->toJpeg(78)->toString();

            $path = self::DIR . '/' . Str::ulid() . '.jpg';
            Storage::disk($disk)->put($path, $content, [
                'visibility'   => in_array($disk, ['r2', 's3']) ? 'private' : 'public',
                'CacheControl' => self::CACHE,
            ]);

            return $path;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Point an item's thumbnail_path at a fresh thumbnail of its current image. */
    public function refreshFor(ContentItem $item): bool
    {
        $thumb = $this->generate($item->image_path);
        if (! $thumb) {
            return false;
        }

        $disk = config('filesystems.default', 'public');
        $old  = $item->thumbnail_path;

        $item->thumbnail_path = $thumb;
        $item->saveQuietly();

        // Drop the superseded generated thumbnail (never the full image).
        if ($old && $old !== $item->image_path && str_contains($old, self::DIR)) {
            Storage::disk($disk)->delete($old);
        }

        return true;
    }
}
