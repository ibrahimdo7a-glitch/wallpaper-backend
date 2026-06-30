<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The unified Wallpapers gallery — built on the real content (ContentItem,
 * content_type=wallpapers) linked to brand / car model / brand section /
 * country, NOT the legacy Wallpaper table. Powers /[locale]/wallpapers and its
 * keyword-tag filters, all controllable from the admin settings page.
 */
class WallpaperGalleryController extends Controller
{
    private function base()
    {
        return ContentItem::query()->where('content_type', 'wallpapers')->where('status', 'published');
    }

    // GET /v1/wallpapers-gallery — filtered, paginated grid.
    public function index(Request $request): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json(['data' => [], 'meta' => ['enabled' => false]]);
        }

        $q = $this->base()->with(['brand:id,slug,name_ar', 'brandSection:id,slug', 'carModel:id,name_ar']);

        if ($request->filled('model'))   $q->where('car_model_id', (int) $request->model);
        if ($request->filled('brand'))   $q->where('brand_id', (int) $request->brand);
        if ($request->filled('section')) $q->where('brand_section_id', (int) $request->section);
        if ($request->filled('country')) {
            $c = $request->country;
            $q->whereHas('brand', fn ($b) => $b->where('country', $c));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(fn ($w) => $w->where('title_ar', 'ILIKE', "%{$s}%")->orWhere('title_en', 'ILIKE', "%{$s}%"));
        }

        $sort = match ($request->get('sort', $this->setting('wp_default_sort', 'newest'))) {
            'views'     => ['views_count', 'desc'],
            'downloads' => ['downloads_count', 'desc'],
            default     => ['published_at', 'desc'],
        };
        $q->orderByDesc('is_featured')->orderBy($sort[0], $sort[1]);

        $items = $q->paginate(min(60, (int) $this->setting('wp_per_page', 24)));

        return response()->json([
            'data' => collect($items->items())->map(fn ($i) => $this->card($i))->values(),
            'meta' => [
                'enabled'      => true,
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    // GET /v1/wallpapers-gallery/facets — page config + keyword tags + featured strip.
    public function facets(): JsonResponse
    {
        $config = [
            'enabled'     => $this->enabled(),
            'title_ar'    => $this->setting('wp_title_ar', 'معرض الخلفيات'),
            'title_en'    => $this->setting('wp_title_en', 'Wallpapers'),
            'subtitle_ar' => $this->setting('wp_subtitle_ar', 'أجمل خلفيات السيارات الكهربائية والصينية — مرتّبة حسب الموديل والدولة'),
            'subtitle_en' => $this->setting('wp_subtitle_en', 'The finest electric & Chinese car wallpapers'),
            'default_sort'=> $this->setting('wp_default_sort', 'newest'),
            'show'        => [
                'models'    => $this->bool('wp_show_models', true),
                'countries' => $this->bool('wp_show_countries', true),
                'sections'  => $this->bool('wp_show_sections', true),
                'brands'    => $this->bool('wp_show_brands', false),
                'featured'  => $this->bool('wp_featured_enabled', true),
            ],
        ];

        if (! $config['enabled']) {
            return response()->json(['config' => $config, 'facets' => [], 'featured' => []]);
        }

        $facets = [];
        if ($config['show']['models'])    $facets['models']    = $this->facetModels();
        if ($config['show']['countries']) $facets['countries'] = $this->facetCountries();
        if ($config['show']['sections'])  $facets['sections']  = $this->facetSections();
        if ($config['show']['brands'])    $facets['brands']    = $this->facetBrands();

        $featured = [];
        if ($config['show']['featured']) {
            $featured = $this->base()->where('is_featured', true)
                ->with(['brand:id,slug,name_ar', 'brandSection:id,slug', 'carModel:id,name_ar'])
                ->orderByDesc('published_at')
                ->limit((int) $this->setting('wp_featured_count', 6))
                ->get()->map(fn ($i) => $this->card($i))->values();
        }

        return response()->json(['config' => $config, 'facets' => $facets, 'featured' => $featured]);
    }

    // ─── Facets (only groups that actually have wallpapers) ──────────────────

    private function facetModels(): array
    {
        return $this->base()->whereNotNull('car_model_id')
            ->join('car_models', 'car_models.id', '=', 'content_items.car_model_id')
            ->groupBy('car_models.id', 'car_models.name_ar')
            ->selectRaw('car_models.id as id, car_models.name_ar as label, count(*) as c')
            ->orderByDesc('c')->limit(40)->get()
            ->map(fn ($r) => ['key' => 'model', 'value' => (string) $r->id, 'label' => $r->label, 'count' => (int) $r->c])->toArray();
    }

    private function facetCountries(): array
    {
        return $this->base()->join('brands', 'brands.id', '=', 'content_items.brand_id')
            ->whereNotNull('brands.country')->where('brands.country', '!=', '')
            ->groupBy('brands.country')
            ->selectRaw('brands.country as label, count(*) as c')
            ->orderByDesc('c')->limit(30)->get()
            ->map(fn ($r) => ['key' => 'country', 'value' => $r->label, 'label' => $r->label, 'count' => (int) $r->c])->toArray();
    }

    private function facetSections(): array
    {
        return $this->base()->whereNotNull('brand_section_id')
            ->join('brand_sections', 'brand_sections.id', '=', 'content_items.brand_section_id')
            ->leftJoin('section_types', 'section_types.id', '=', 'brand_sections.section_type_id')
            ->groupBy('brand_sections.id', 'brand_sections.custom_name_ar', 'section_types.name_ar', 'brand_sections.slug')
            ->selectRaw("brand_sections.id as id, coalesce(nullif(brand_sections.custom_name_ar, ''), section_types.name_ar, brand_sections.slug) as label, count(*) as c")
            ->orderByDesc('c')->limit(40)->get()
            ->map(fn ($r) => ['key' => 'section', 'value' => (string) $r->id, 'label' => $r->label, 'count' => (int) $r->c])->toArray();
    }

    private function facetBrands(): array
    {
        return $this->base()->join('brands', 'brands.id', '=', 'content_items.brand_id')
            ->groupBy('brands.id', 'brands.name_ar')
            ->selectRaw('brands.id as id, brands.name_ar as label, count(*) as c')
            ->orderByDesc('c')->limit(30)->get()
            ->map(fn ($r) => ['key' => 'brand', 'value' => (string) $r->id, 'label' => $r->label, 'count' => (int) $r->c])->toArray();
    }

    private function card(ContentItem $i): array
    {
        return [
            'id'           => $i->id,
            'title'        => $i->title_ar ?: $i->title_en,
            'thumbnail'    => $i->thumbnail_url ?: $i->image_url,
            'image'        => $i->image_url,
            'brand_slug'   => $i->brand?->slug,
            'section_slug' => $i->brandSection?->slug,
            'model'        => $i->carModel?->name_ar,
            'is_featured'  => (bool) $i->is_featured,
            'views'        => (int) $i->views_count,
            'downloads'    => (int) $i->downloads_count,
        ];
    }

    // ─── Settings helpers ────────────────────────────────────────────────────

    private function enabled(): bool
    {
        return $this->bool('wp_enabled', true);
    }

    private function setting(string $key, $default)
    {
        $v = Setting::get($key, '');
        return ($v === '' || $v === null) ? $default : $v;
    }

    private function bool(string $key, bool $default): bool
    {
        $v = Setting::get($key, null);
        return $v === null || $v === '' ? $default : filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
