<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    // ─── GET /api/v1/content/{id} ─────────────────────────────────────────────
    public function show(int $id): JsonResponse
    {
        $item = ContentItem::with(['brand', 'brandSection.sectionType', 'collection', 'carModel', 'designer'])
            ->where('status', 'published')
            ->findOrFail($id);

        // NOTE: views are counted client-side via POST /content/{id}/view (real human views only).
        // Incrementing here inflated the count on every server render / ISR revalidation.

        $related = ContentItem::where('brand_section_id', $item->brand_section_id)
            ->where('status', 'published')
            ->where('id', '!=', $item->id)
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(12)
            ->get()
            ->map(fn($r) => $this->card($r));

        return response()->json([
            'data'    => $this->detail($item),
            'related' => $related,
        ]);
    }

    // ─── POST /api/v1/content/{id}/view ───────────────────────────────────────
    public function view(int $id): JsonResponse
    {
        ContentItem::where('id', $id)->where('status', 'published')->increment('views_count');

        return response()->json(['ok' => true]);
    }

    // ─── POST /api/v1/content/{id}/like ───────────────────────────────────────
    public function like(int $id): JsonResponse
    {
        $item = ContentItem::where('status', 'published')->findOrFail($id);
        $item->increment('likes_count');

        return response()->json(['likes_count' => $item->likes_count]);
    }

    // ─── POST /api/v1/content/{id}/download ───────────────────────────────────
    public function download(int $id): JsonResponse
    {
        $item = ContentItem::where('status', 'published')->findOrFail($id);
        $item->increment('downloads_count');

        return response()->json([
            'download_url'    => $item->file_url ?? $item->image_url,
            'downloads_count' => $item->downloads_count,
        ]);
    }

    // ─── Serializers ──────────────────────────────────────────────────────────
    private function detail(ContentItem $i): array
    {
        return [
            'id'              => $i->id,
            'title_ar'        => $i->title_ar,
            'title_en'        => $i->title_en,
            'slug'            => $i->slug,
            'description_ar'  => $i->description_ar,
            'description_en'  => $i->description_en,
            'author_name'     => $i->author_name,
            'designer'        => $i->designer ? [
                'id'         => $i->designer->id,
                'name_ar'    => $i->designer->name_ar,
                'name_en'    => $i->designer->name_en,
                'avatar_url' => $i->designer->avatar_url,
                'telegram_url' => $i->designer->telegram_url,
            ] : null,
            'image_url'       => $i->image_url,
            'thumbnail_url'   => $i->thumbnail_url,
            'file_url'        => $i->file_url,
            'file_size_label' => $i->file_size_label,
            'video_url'       => $i->video_url,
            'external_url'    => $i->external_url,
            'metadata'        => $i->metadata,
            'content_type'    => $i->content_type,
            'is_featured'     => $i->is_featured,
            'views_count'     => $i->views_count,
            'downloads_count' => $i->downloads_count,
            'likes_count'     => $i->likes_count,
            'published_at'    => $i->published_at,
            'brand'           => $i->brand ? [
                'name_ar' => $i->brand->name_ar,
                'name_en' => $i->brand->name_en,
                'slug'    => $i->brand->slug,
                'logo_url'=> $i->brand->logo_url,
                'primary_color' => $i->brand->primary_color,
            ] : null,
            'section'         => $i->brandSection ? [
                'name_ar' => $i->brandSection->getNameAr(),
                'name_en' => $i->brandSection->getNameEn(),
                'slug'    => $i->brandSection->slug,
                'icon'    => $i->brandSection->getIcon(),
            ] : null,
            'collection'      => $i->collection ? [
                'name_ar' => $i->collection->name_ar,
                'name_en' => $i->collection->name_en,
                'slug'    => $i->collection->slug,
                'icon'    => $i->collection->icon,
            ] : null,
            // When the item belongs to a model, the frontend uses this to link
            // back to the model's section page instead of the brand-level one.
            'model'           => $i->carModel ? [
                'name_ar' => $i->carModel->name_ar,
                'name_en' => $i->carModel->name_en,
                'slug'    => $i->carModel->slug,
            ] : null,
        ];
    }

    private function card(ContentItem $i): array
    {
        return [
            'id'              => $i->id,
            'title_ar'        => $i->title_ar,
            'title_en'        => $i->title_en,
            'slug'            => $i->slug,
            'image_url'       => $i->image_url,
            'thumbnail_url'   => $i->thumbnail_url,
            'downloads_count' => $i->downloads_count,
            'metadata'        => $i->metadata,
        ];
    }
}
