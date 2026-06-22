<?php

namespace App\Services;

use App\Models\BrandSection;
use App\Models\ContentItem;
use App\Models\Wallpaper;

/**
 * Converts a legacy Wallpaper (wallpapers table) into a Brand Builder
 * ContentItem (content_items table) linked to a brand + section + collection.
 * Reuses the same stored image files — no re-upload needed.
 */
class WallpaperConverter
{
    /**
     * @param  array{brand_id:int, brand_section_id:int, content_collection_id:?int, car_model_id:?int, delete_original?:bool}  $target
     */
    public static function convert(Wallpaper $wallpaper, array $target): ?ContentItem
    {
        $section = BrandSection::with('sectionType')->find($target['brand_section_id']);
        if (!$section) {
            return null;
        }

        // Skip if this wallpaper was already converted to this exact section
        $already = ContentItem::where('brand_section_id', $section->id)
            ->whereJsonContains('metadata->source_wallpaper_id', $wallpaper->id)
            ->first();
        if ($already) {
            return $already;
        }

        $item = ContentItem::create([
            'brand_id'              => $target['brand_id'],
            'brand_section_id'      => $section->id,
            'content_collection_id' => $target['content_collection_id'] ?? null,
            'car_model_id'          => $target['car_model_id'] ?? null,
            'content_type'          => $section->sectionType?->key ?? 'wallpapers',
            'title_ar'              => $wallpaper->title_ar ?: ('خلفية #' . $wallpaper->id),
            'title_en'              => $wallpaper->title_en,
            'description_ar'        => $wallpaper->description_ar,
            'description_en'        => $wallpaper->description_en,
            'image_path'            => $wallpaper->original_file,
            'thumbnail_path'        => $wallpaper->thumbnail_file ?: $wallpaper->original_file,
            'file_path'             => $wallpaper->original_file,   // downloadable
            'metadata'              => array_filter([
                'resolution'          => $wallpaper->resolution_label,
                'width'               => $wallpaper->width,
                'height'              => $wallpaper->height,
                'file_size'           => $wallpaper->file_size,
                'source_wallpaper_id' => $wallpaper->id,
            ]),
            'status'                => $wallpaper->status === 'published' ? 'published' : 'draft',
            'is_featured'           => (bool) $wallpaper->is_featured,
            'is_pinned'             => false,
            'sort_order'            => 0,
            'views_count'           => (int) $wallpaper->views_count,
            'downloads_count'       => (int) $wallpaper->downloads_count,
            'published_at'          => $wallpaper->published_at,
        ]);

        if (!empty($target['delete_original'])) {
            $wallpaper->delete(); // soft delete — files stay intact for the new content item
        }

        return $item;
    }
}
