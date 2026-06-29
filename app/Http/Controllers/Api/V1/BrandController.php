<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AndroidApp;
use App\Models\Brand;
use App\Models\BrandSection;
use App\Models\CarModel;
use App\Models\ContentCollection;
use App\Models\ContentItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    // ─── GET /api/v1/brands ───────────────────────────────────────────────────
    public function index(Request $request)
    {
        $cacheKey = 'brands.list.' . ($request->boolean('featured') ? 'featured' : 'all');

        $brands = Cache::remember($cacheKey, 300, function () use ($request) {
            return Brand::active()
                ->when($request->boolean('featured'), fn($q) => $q->featured())
                ->orderBy('sort_order')
                ->get()
                ->map(fn($b) => $this->brandCard($b));
        });

        return response()->json(['data' => $brands]);
    }

    // ─── GET /api/v1/brands/{slug} ────────────────────────────────────────────
    public function show(string $slug)
    {
        $brand = Cache::remember("brand.{$slug}", 300, function () use ($slug) {
            $b = Brand::active()->where('slug', $slug)->firstOrFail();
            return array_merge($this->brandCard($b), [
                'description_ar'         => $b->description_ar,
                'description_en'         => $b->description_en,
                'website_url'            => $b->website_url,
                'telegram_url'           => $b->telegram_url,
                'whatsapp_url'           => $b->whatsapp_url,
                'channel_url'            => $b->channel_url,
                'download_cta_url'       => $b->download_cta_url,
                'download_cta_label_ar'  => $b->download_cta_label_ar,
                'download_cta_label_en'  => $b->download_cta_label_en,
                'primary_color'          => $b->primary_color,
                'accent_color'           => $b->accent_color,
                'news_count'             => $b->news_count,
                'tutorials_count'        => $b->tutorials_count,
                'total_downloads'        => $b->total_downloads,
                'meta_title_ar'          => $b->meta_title_ar,
                'meta_title_en'          => $b->meta_title_en,
                'meta_description_ar'    => $b->meta_description_ar,
                'meta_description_en'    => $b->meta_description_en,
            ]);
        });

        return response()->json(['data' => $brand]);
    }

    // ─── GET /api/v1/brands/{slug}/sections ───────────────────────────────────
    public function sections(string $slug)
    {
        $brand = Brand::active()->where('slug', $slug)->firstOrFail();

        $sections = Cache::remember("brand.{$slug}.sections", 300, function () use ($brand) {
            return $brand->enabledSections()
                ->with('sectionType')
                ->get()
                ->map(fn($s) => $this->sectionCard($s));
        });

        return response()->json(['data' => $sections]);
    }

    // ─── GET /api/v1/brands/{slug}/sections/{sectionSlug} ────────────────────
    public function sectionContent(string $brandSlug, string $sectionSlug, Request $request)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();

        $section = BrandSection::where('brand_id', $brand->id)
            ->where('slug', $sectionSlug)
            ->where('is_enabled', true)
            ->with('sectionType')
            ->firstOrFail();

        $perPage = min((int)$request->get('per_page', 20), 100);

        // Collections (sub-folders) available in this section — brand-level only
        $collections = ContentCollection::where('brand_id', $brand->id)
            ->whereNull('car_model_id')
            ->where('is_active', true)
            ->where(fn($q) => $q->where('brand_section_id', $section->id)->orWhereNull('brand_section_id'))
            ->orderBy('sort_order')
            ->withCount(['contentItems' => fn($q) => $q->where('status', 'published')])
            ->get()
            ->map(fn($c) => $this->collectionCard($c))
            ->filter(fn($c) => $c['items_count'] > 0)   // only show non-empty folders
            ->values();

        // Optional: filter content by a specific collection slug
        $activeCollection = null;
        $query = ContentItem::where('brand_section_id', $section->id)
            ->where('status', 'published')
            ->whereNull('car_model_id');   // brand-level items only

        if ($collectionSlug = $request->get('collection')) {
            $activeCollection = ContentCollection::where('brand_id', $brand->id)
                ->where('slug', $collectionSlug)->first();
            if ($activeCollection) {
                $query->where('content_collection_id', $activeCollection->id);
            }
        }

        $items = $query->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'section'           => $this->sectionCard($section),
            'brand'             => ['name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'slug' => $brand->slug],
            'collections'       => $collections,
            'active_collection' => $activeCollection ? $this->collectionCard($activeCollection) : null,
            'data'              => $items->map(fn($i) => $this->contentCard($i)),
            'meta'    => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    // ─── GET /api/v1/brands/{slug}/models ─────────────────────────────────────
    public function models(string $slug, Request $request)
    {
        $brand = Brand::active()->where('slug', $slug)->firstOrFail();

        $models = Cache::remember("brand.{$slug}.models", 300, function () use ($brand, $request) {
            return $brand->carModels()
                ->where('is_active', true)
                ->when($request->get('car_type'), fn($q, $v) => $q->where('car_type', $v))
                ->when($request->get('fuel_type'), fn($q, $v) => $q->where('fuel_type', $v))
                ->get()
                ->map(fn($m) => $this->modelCard($m));
        });

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'slug' => $brand->slug],
            'data'  => $models,
        ]);
    }

    // ─── GET /api/v1/brands/{slug}/models/{modelSlug} ─────────────────────────
    public function modelShow(string $brandSlug, string $modelSlug)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        // Sections that are model-specific AND enabled for this brand.
        // If the model picked specific sections, show only those; otherwise show all.
        $visibleIds = $model->visibleSections()->pluck('brand_sections.id');

        $sections = BrandSection::where('brand_id', $brand->id)
            ->where('is_enabled', true)
            ->where('is_model_specific', true)
            ->when($visibleIds->isNotEmpty(), fn($q) => $q->whereIn('id', $visibleIds))
            ->with('sectionType')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($s) => $this->sectionCard($s));

        // Last 12 marketplace listings that picked THIS model (featured first).
        $listings = \App\Models\MarketListing::where('car_model_id', $model->id)
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(12)
            ->get()
            ->map(fn ($l) => [
                'id'           => $l->id,
                'title_ar'     => $l->title_ar,
                'title_en'     => $l->title_en,
                'slug'         => $l->slug,
                'price'        => $l->price !== null ? (float) $l->price : null,
                'currency'     => $l->currency,
                'city'         => $l->city,
                'cover_url'    => $l->cover_url,
                'is_featured'  => (bool) $l->is_featured,
                'listing_type' => $l->listing_type,
            ]);

        // Latest wallpapers that picked THIS model.
        $wallpapers = ContentItem::where('car_model_id', $model->id)
            ->where('content_type', 'wallpapers')
            ->where('status', 'published')
            ->with('brandSection:id,slug')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(fn ($i) => $this->contentCard($i) + ['section_slug' => $i->brandSection?->slug]);

        return response()->json([
            'data' => array_merge($this->modelCard($model), [
                'description_ar' => $model->description_ar,
                'description_en' => $model->description_en,
                'brand' => ['name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'slug' => $brand->slug, 'logo_url' => $brand->logo_url, 'primary_color' => $brand->primary_color],
            ]),
            'sections'   => $sections,
            'listings'   => $listings,
            'wallpapers' => $wallpapers,
        ]);
    }

    // ─── GET /api/v1/brands/{slug}/models/{modelSlug}/sections/{sectionSlug} ──
    public function modelSectionContent(string $brandSlug, string $modelSlug, string $sectionSlug, Request $request)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        $section = BrandSection::where('brand_id', $brand->id)
            ->where('slug', $sectionSlug)
            ->where('is_enabled', true)
            ->firstOrFail();

        $perPage = min((int)$request->get('per_page', 20), 100);

        // Sub-sections (collections) for THIS model + section, only non-empty ones
        $collections = ContentCollection::where('brand_id', $brand->id)
            ->where('car_model_id', $model->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->withCount(['contentItems' => fn($q) => $q->where('status', 'published')->where('brand_section_id', $section->id)])
            ->get()
            ->map(fn($c) => $this->collectionCard($c))
            ->filter(fn($c) => $c['items_count'] > 0)
            ->values();

        $activeCollection = null;
        $query = ContentItem::where('brand_section_id', $section->id)
            ->where('car_model_id', $model->id)
            ->where('status', 'published');

        if ($collectionSlug = $request->get('collection')) {
            $activeCollection = ContentCollection::where('brand_id', $brand->id)
                ->where('car_model_id', $model->id)
                ->where('slug', $collectionSlug)->first();
            if ($activeCollection) {
                $query->where('content_collection_id', $activeCollection->id);
            }
        }

        $items = $query->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'section'           => $this->sectionCard($section),
            'brand'             => ['name_ar' => $brand->name_ar, 'slug' => $brand->slug],
            'model'             => ['name_ar' => $model->name_ar, 'slug' => $model->slug],
            'collections'       => $collections,
            'active_collection' => $activeCollection ? $this->collectionCard($activeCollection) : null,
            'data'              => $items->map(fn($i) => $this->contentCard($i)),
            'meta'    => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────
    private function brandCard(Brand $b): array
    {
        return [
            'id'               => $b->id,
            'name_ar'          => $b->name_ar,
            'name_en'          => $b->name_en,
            'slug'             => $b->slug,
            'logo_url'         => $b->logo_url,
            'cover_image_url'  => $b->cover_image_url,
            'country'          => $b->country,
            'is_featured'      => $b->is_featured,
            'models_count'     => $b->models_count,
            'wallpapers_count' => $b->wallpapers_count,
            'apps_count'       => $b->apps_count,
        ];
    }

    private function sectionCard(BrandSection $s): array
    {
        return [
            'id'              => $s->id,
            'slug'            => $s->slug,
            'name_ar'         => $s->getNameAr(),
            'name_en'         => $s->getNameEn(),
            'icon'            => $s->getIcon(),
            'description_ar'  => $s->custom_description_ar ?? $s->sectionType?->description_ar,
            'description_en'  => $s->custom_description_en ?? $s->sectionType?->description_en,
            'cover_image_url' => $s->cover_image_url,
            'layout_type'     => $s->layout_type,
            'is_model_specific' => $s->is_model_specific,
            'show_in_navigation' => $s->show_in_navigation,
            'show_in_brand_home' => $s->show_in_brand_home,
            'sort_order'      => $s->sort_order,
            'settings'        => $s->settings,
        ];
    }

    // ─── GET /api/v1/brands/{slug}/apps — apps linked to this brand ───────────
    public function apps(string $slug)
    {
        $brand = Brand::active()->where('slug', $slug)->firstOrFail();

        $apps = $brand->linkedApps()
            ->where('status', 'published')
            ->with('category:id,name_ar,name_en,slug,icon')
            ->orderByDesc('is_featured')->orderBy('apps.sort_order')->orderByDesc('apps.created_at')
            ->get()
            ->map(fn (AndroidApp $a) => $this->appCard($a))
            ->values();

        return response()->json(['data' => $apps]);
    }

    private function appCard(AndroidApp $app): array
    {
        $disk = config('filesystems.default', 'public');
        return [
            'id'                   => $app->id,
            'title_ar'             => $app->title_ar,
            'title_en'             => $app->title_en,
            'slug'                 => $app->slug,
            'short_description_ar' => $app->short_description_ar,
            'short_description_en' => $app->short_description_en,
            'badge_text_ar'        => $app->badge_text_ar,
            'badge_text_en'        => $app->badge_text_en,
            'works_on_car_screen'  => (bool) $app->works_on_car_screen,
            'icon_url'             => $app->icon_file ? Storage::disk($disk)->url($app->icon_file) : null,
            'cover_image_url'      => $app->cover_image ? Storage::disk($disk)->url($app->cover_image) : null,
            'version'              => $app->version,
            'developer'            => $app->developer,
            'min_android'          => $app->min_android,
            'file_size_label'      => $app->file_size_label,
            'is_free'              => $app->is_free,
            'is_featured'          => (bool) $app->is_featured,
            'is_important'         => (bool) $app->is_important,
            'is_verified'          => (bool) $app->is_verified,
            'sort_order'           => (int) $app->sort_order,
            'safety_status'        => $app->safety_status,
            'downloads_count'      => $app->downloads_count,
            'published_at'         => $app->published_at?->toISOString(),
            'category'             => $app->category ? [
                'id'      => $app->category->id,
                'name_ar' => $app->category->name_ar,
                'name_en' => $app->category->name_en,
                'slug'    => $app->category->slug,
                'icon'    => $app->category->icon,
            ] : null,
        ];
    }

    private function contentCard(ContentItem $i): array
    {
        return [
            'id'              => $i->id,
            'title_ar'        => $i->title_ar,
            'title_en'        => $i->title_en,
            'slug'            => $i->slug,
            'description_ar'  => $i->description_ar,
            'description_en'  => $i->description_en,
            'image_url'       => $i->image_url,
            'thumbnail_url'   => $i->thumbnail_url,
            'file_url'        => $i->file_url,
            'file_size_label' => $i->file_size_label,
            'video_url'       => $i->video_url,
            'external_url'    => $i->external_url,
            'metadata'        => $i->metadata,
            'content_type'    => $i->content_type,
            'collection_id'   => $i->content_collection_id,
            'is_featured'     => $i->is_featured,
            'is_pinned'       => $i->is_pinned,
            'views_count'     => $i->views_count,
            'downloads_count' => $i->downloads_count,
            'published_at'    => $i->published_at,
        ];
    }

    private function collectionCard(ContentCollection $c): array
    {
        return [
            'id'          => $c->id,
            'name_ar'     => $c->name_ar,
            'name_en'     => $c->name_en,
            'slug'        => $c->slug,
            'icon'        => $c->icon,
            'image_url'   => $c->image_url,
            'description_ar' => $c->description_ar,
            'description_en' => $c->description_en,
            'items_count' => $c->content_items_count ?? $c->contentItems()->where('status', 'published')->count(),
        ];
    }

    private function modelCard(CarModel $m): array
    {
        return [
            'id'               => $m->id,
            'name_ar'          => $m->name_ar,
            'name_en'          => $m->name_en,
            'slug'             => $m->slug,
            'image_url'        => $m->image_url,
            'cover_image_url'  => $m->cover_image_url,
            'car_type'         => $m->car_type,
            'fuel_type'        => $m->fuel_type,
            'year_from'        => $m->year_from,
            'year_to'          => $m->year_to,
            'year_label'       => $m->year_label,
            'is_featured'      => $m->is_featured,
        ];
    }
}
