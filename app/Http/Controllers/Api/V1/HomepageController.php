<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AndroidApp;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\ContentItem;
use App\Models\HeroBanner;
use App\Models\HomepageSection;
use App\Models\NavigationItem;
use App\Models\NewsArticle;
use App\Models\Wallpaper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $sections = Cache::remember("homepage.data.{$locale}", 120, function () {
            return HomepageSection::active()
                ->get()
                ->map(fn($s) => array_merge($this->sectionBase($s), ['data' => $this->resolveData($s)]))
                ->values();
        });

        return response()->json(['data' => $sections]);
    }

    public function navigation(): JsonResponse
    {
        $items = Cache::remember('navigation.items', 300, function () {
            return NavigationItem::active()
                ->with(['children' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
                ->get()
                ->map(fn($item) => $this->navCard($item));
        });

        return response()->json(['data' => $items]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim($request->string('q'));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $types  = $request->array('types') ?: ['brands', 'models', 'apps', 'news'];
        $results = [];

        if (in_array('brands', $types)) {
            Brand::active()
                ->where(fn($query) => $query->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%"))
                ->limit(5)->get()
                ->each(fn($b) => $results[] = ['type' => 'brand', 'id' => $b->id, 'title' => $b->name_ar, 'slug' => $b->slug, 'image' => $b->logo_url]);
        }

        if (in_array('models', $types)) {
            CarModel::where('is_active', true)
                ->where(fn($query) => $query->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%"))
                ->with('brand:id,slug,name_ar')
                ->limit(5)->get()
                ->each(fn($m) => $results[] = ['type' => 'model', 'id' => $m->id, 'title' => $m->name_ar, 'slug' => $m->slug, 'brand_slug' => $m->brand?->slug, 'image' => $m->image_url]);
        }

        if (in_array('apps', $types)) {
            AndroidApp::where('status', 'published')
                ->where(fn($query) => $query->where('title_ar', 'like', "%{$q}%")->orWhere('title_en', 'like', "%{$q}%"))
                ->limit(5)->get()
                ->each(fn($a) => $results[] = ['type' => 'app', 'id' => $a->id, 'title' => $a->title_ar, 'slug' => $a->slug, 'image' => $a->icon_url]);
        }

        if (in_array('news', $types)) {
            NewsArticle::where('status', 'published')
                ->where(fn($query) => $query->where('title_ar', 'like', "%{$q}%")->orWhere('title_en', 'like', "%{$q}%"))
                ->limit(3)->get()
                ->each(fn($n) => $results[] = ['type' => 'news', 'id' => $n->id, 'title' => $n->title_ar, 'slug' => $n->slug, 'image' => $n->cover_image_url]);
        }

        return response()->json(['data' => $results]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function sectionBase(HomepageSection $s): array
    {
        return [
            'id'          => $s->id,
            'type'        => $s->type,
            'name'        => $s->name,
            'title_ar'    => $s->title_ar,
            'title_en'    => $s->title_en,
            'subtitle_ar' => $s->subtitle_ar,
            'subtitle_en' => $s->subtitle_en,
            'layout'      => $s->layout,
            'visibility'  => $s->visibility,
            'settings'    => $s->settings,
            'sort_order'  => $s->sort_order,
        ];
    }

    private function resolveData(HomepageSection $s): mixed
    {
        return match ($s->type) {
            'hero'                => $this->heroData($s),
            'brands'              => $this->brandsData($s),
            'featured_brands'     => $this->featuredBrandsData($s),
            'latest_wallpapers'   => $this->latestWallpapersData($s),
            'featured_wallpapers' => $this->wallpapersData($s),
            'featured_apps'       => $this->appsData($s),
            'news'                => $this->newsData($s),
            'statistics'          => $this->statisticsData($s),
            default               => $s->settings ?? [],
        };
    }

    private function heroData(HomepageSection $s): array
    {
        $hero = HeroBanner::active()->first();
        if (!$hero) return [];
        return [
            'title_ar'               => $hero->title_ar,
            'title_en'               => $hero->title_en,
            'subtitle_ar'            => $hero->subtitle_ar,
            'subtitle_en'            => $hero->subtitle_en,
            'description_ar'         => $hero->description_ar,
            'description_en'         => $hero->description_en,
            'image_url'              => $hero->image_url,
            'bg_color'               => $hero->bg_color,
            'text_color'             => $hero->text_color,
            'primary_btn_label_ar'   => $hero->primary_btn_label_ar,
            'primary_btn_label_en'   => $hero->primary_btn_label_en,
            'primary_btn_url'        => $hero->primary_btn_url,
            'secondary_btn_label_ar' => $hero->secondary_btn_label_ar,
            'secondary_btn_label_en' => $hero->secondary_btn_label_en,
            'secondary_btn_url'      => $hero->secondary_btn_url,
        ];
    }

    private function brandsData(HomepageSection $s): array
    {
        $limit = $s->settings['limit'] ?? 20;
        return ['items' => Brand::active()->orderBy('sort_order')->limit($limit)->get()->map(fn($b) => $this->brandCard($b))->values()];
    }

    private function featuredBrandsData(HomepageSection $s): array
    {
        $limit = $s->settings['limit'] ?? 8;
        return ['items' => Brand::active()->where('is_featured', true)->orderBy('sort_order')->limit($limit)->get()->map(fn($b) => $this->brandCard($b))->values()];
    }

    private function latestWallpapersData(HomepageSection $s): array
    {
        $limit = $s->settings['limit'] ?? 12;
        $items = ContentItem::where('content_type', 'wallpapers')
            ->where('status', 'published')
            ->with(['brand:id,slug,name_ar', 'brandSection:id,slug'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($i) => [
                'id'               => $i->id,
                'title_ar'         => $i->title_ar,
                'title_en'         => $i->title_en,
                'slug'             => $i->slug,
                'image_url'        => $i->image_url,
                'thumbnail_url'    => $i->thumbnail_url,
                'downloads_count'  => $i->downloads_count,
                'resolution_label' => $i->metadata['resolution'] ?? null,
                'brand_slug'       => $i->brand?->slug,
                'section_slug'     => $i->brandSection?->slug,
                'type'             => 'wallpaper',
            ]);
        return ['items' => $items->values()];
    }

    private function wallpapersData(HomepageSection $s): array
    {
        $limit = $s->settings['limit'] ?? 10;
        $items = ContentItem::where('content_type', 'wallpapers')
            ->where('is_featured', true)->where('status', 'published')
            ->with(['brand:id,slug', 'brandSection:id,slug'])
            ->orderByDesc('downloads_count')->limit($limit)->get()
            ->map(fn($i) => [
                'id'               => $i->id,
                'title_ar'         => $i->title_ar,
                'title_en'         => $i->title_en,
                'slug'             => $i->slug,
                'thumbnail_url'    => $i->thumbnail_url,
                'image_url'        => $i->image_url,
                'downloads_count'  => $i->downloads_count,
                'resolution_label' => $i->metadata['resolution'] ?? null,
                'brand_slug'       => $i->brand?->slug,
                'section_slug'     => $i->brandSection?->slug,
                'type'             => 'wallpaper',
            ]);
        return ['items' => $items->values()];
    }

    private function appsData(HomepageSection $s): array
    {
        $limit = $s->settings['limit'] ?? 8;
        $items = AndroidApp::where('is_featured', true)->where('status', 'published')
            ->orderByDesc('downloads_count')->limit($limit)->get()
            ->map(fn($a) => [
                'id'              => $a->id,
                'title_ar'        => $a->title_ar,
                'title_en'        => $a->title_en,
                'slug'            => $a->slug,
                'icon_url'        => $a->icon_url,
                'version'         => $a->version,
                'badge_text_ar'   => $a->badge_text_ar,
                'badge_text_en'   => $a->badge_text_en,
                'is_free'         => $a->is_free,
                'downloads_count' => $a->downloads_count,
                'type'            => 'app',
            ]);
        return ['items' => $items->values()];
    }

    private function newsData(HomepageSection $s): array
    {
        $limit        = $s->settings['limit'] ?? 6;
        $featuredOnly = $s->settings['featured_only'] ?? true;
        $query = NewsArticle::where('status', 'published')->with('category')->orderByDesc('published_at')->limit($limit);
        if ($featuredOnly) $query->where('is_featured', true);
        $items = $query->get()->map(fn($a) => [
            'id'              => $a->id,
            'title_ar'        => $a->title_ar,
            'title_en'        => $a->title_en,
            'slug'            => $a->slug,
            'summary_ar'      => $a->summary_ar,
            'summary_en'      => $a->summary_en,
            'cover_image_url' => $a->cover_image_url,
            'is_breaking'     => $a->is_breaking,
            'views_count'     => $a->views_count,
            'published_at'    => $a->published_at?->toISOString(),
            'category'        => $a->category ? ['name_ar' => $a->category->name_ar, 'slug' => $a->category->slug, 'color' => $a->category->color] : null,
            'type'            => 'news',
        ]);
        return ['items' => $items->values()];
    }

    private function statisticsData(HomepageSection $s): array
    {
        $ov = $s->settings ?? [];
        return ['items' => [
            ['key' => 'downloads', 'icon' => '⬇️', 'label_ar' => 'تحميل',  'label_en' => 'Downloads',  'value' => $ov['override_downloads']  ?? Wallpaper::sum('downloads_count'), 'prefix' => '+'],
            ['key' => 'apps',      'icon' => '📱', 'label_ar' => 'تطبيق',  'label_en' => 'Apps',       'value' => $ov['override_apps']       ?? AndroidApp::where('status', 'published')->count(), 'prefix' => '+'],
            ['key' => 'wallpapers','icon' => '🖼️', 'label_ar' => 'خلفية',  'label_en' => 'Wallpapers', 'value' => $ov['override_wallpapers']  ?? Wallpaper::where('status', 'published')->count(), 'prefix' => '+'],
            ['key' => 'models',    'icon' => '🚗', 'label_ar' => 'موديل',  'label_en' => 'Models',     'value' => $ov['override_models']      ?? CarModel::where('is_active', true)->count(), 'prefix' => '+'],
            ['key' => 'brands',    'icon' => '🏭', 'label_ar' => 'ماركة',  'label_en' => 'Brands',     'value' => $ov['override_brands']      ?? Brand::where('is_active', true)->count(), 'prefix' => '+'],
        ]];
    }

    private function brandCard(Brand $b): array
    {
        return [
            'id'            => $b->id,
            'name_ar'       => $b->name_ar,
            'name_en'       => $b->name_en,
            'slug'          => $b->slug,
            'logo_url'      => $b->logo_url,
            'primary_color' => $b->primary_color,
            'models_count'  => $b->models_count,
            'is_featured'   => $b->is_featured,
        ];
    }

    private function navCard(NavigationItem $item): array
    {
        return [
            'id'              => $item->id,
            'label_ar'        => $item->label_ar,
            'label_en'        => $item->label_en,
            'url'             => $item->url,
            'icon'            => $item->icon,
            'open_in_new_tab' => $item->open_in_new_tab,
            'children'        => $item->children->map(fn($c) => [
                'id'              => $c->id,
                'label_ar'        => $c->label_ar,
                'label_en'        => $c->label_en,
                'url'             => $c->url,
                'icon'            => $c->icon,
                'open_in_new_tab' => $c->open_in_new_tab,
            ])->values(),
        ];
    }
}
