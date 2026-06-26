<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    // Two separate sections, each with its own types + toggle.
    private const SECTION_TYPES = [
        'cars'  => ['car_sale', 'car_request'],
        'parts' => ['part', 'accessory', 'service'],
    ];

    private function sectionEnabled(string $section): bool
    {
        return filter_var(Setting::get($section === 'cars' ? 'cars_enabled' : 'parts_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    // GET /v1/market/config
    public function config(): JsonResponse
    {
        return response()->json([
            'cars' => [
                'enabled'  => $this->sectionEnabled('cars'),
                'label_ar' => Setting::get('cars_label_ar') ?: 'سوق السيارات',
                'label_en' => Setting::get('cars_label_en') ?: 'Cars',
            ],
            'parts' => [
                'enabled'  => $this->sectionEnabled('parts'),
                'label_ar' => Setting::get('parts_label_ar') ?: 'قطع وأكسسوارات',
                'label_en' => Setting::get('parts_label_en') ?: 'Parts & Accessories',
            ],
        ]);
    }

    // GET /v1/market?section=cars|parts
    public function index(Request $request): JsonResponse
    {
        $section = $request->section === 'parts' ? 'parts' : 'cars';
        if (! $this->sectionEnabled($section)) {
            return response()->json(['data' => [], 'meta' => ['enabled' => false]]);
        }

        $sectionTypes = self::SECTION_TYPES[$section];
        $query = MarketListing::published()->whereIn('listing_type', $sectionTypes);

        if ($request->type && in_array($request->type, $sectionTypes, true)) {
            $query->where('listing_type', $request->type);
        }
        if ($request->category) {
            $catId = is_numeric($request->category) ? $request->category
                : MarketCategory::where('slug', $request->category)->value('id');
            if ($catId) $query->where('market_category_id', $catId);
        }
        if ($request->brand) {
            $bId = is_numeric($request->brand) ? $request->brand
                : Brand::where('slug', $request->brand)->value('id');
            if ($bId) $query->where('brand_id', $bId);
        }
        if ($request->city)      $query->where('city', $request->city);
        if ($request->country)   $query->where('country', $request->country);
        if ($request->condition) $query->where('condition', $request->condition);
        if ($request->filled('min_price')) $query->where('price', '>=', (float) $request->min_price);
        if ($request->filled('max_price')) $query->where('price', '<=', (float) $request->max_price);
        if ($request->search) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('title_ar', 'ILIKE', "%{$s}%")->orWhere('title_en', 'ILIKE', "%{$s}%"));
        }

        $sort = match ($request->sort) {
            'price_low'  => ['price', 'asc'],
            'price_high' => ['price', 'desc'],
            default      => ['published_at', 'desc'],
        };
        $query->orderByDesc('is_featured')->orderBy($sort[0], $sort[1]);

        $items = $query->paginate($request->integer('per_page', 24));

        return response()->json([
            'data' => collect($items->items())->map(fn ($l) => $this->card($l))->values(),
            'meta' => [
                'enabled'      => true,
                'section'      => $section,
                'types'        => $sectionTypes,
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    // GET /v1/market/{slug}
    public function show(string $slug): JsonResponse
    {
        $l = MarketListing::published()
            ->with(['brand:id,name_ar,name_en,slug', 'carModel:id,name_ar,name_en', 'category:id,name_ar,name_en'])
            ->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $this->detail($l)]);
    }

    // POST /v1/market/{id}/view  (client-side only — counters must never live in a GET)
    public function view(int $id): JsonResponse
    {
        MarketListing::where('id', $id)->where('status', 'published')->increment('views_count');
        return response()->json(['ok' => true]);
    }

    // GET /v1/market-categories
    public function categories(): JsonResponse
    {
        $cats = MarketCategory::active()->get(['id', 'listing_type', 'name_ar', 'name_en', 'slug', 'icon']);
        return response()->json(['data' => $cats]);
    }

    private function card(MarketListing $l): array
    {
        return [
            'id'            => $l->id,
            'listing_type'  => $l->listing_type,
            'title_ar'      => $l->title_ar,
            'title_en'      => $l->title_en,
            'slug'          => $l->slug,
            'price'         => $l->price !== null ? (float) $l->price : null,
            'currency'      => $l->currency,
            'is_negotiable' => (bool) $l->is_negotiable,
            'condition'     => $l->condition,
            'country'       => $l->country,
            'city'          => $l->city,
            'cover_url'     => $l->cover_url,
            'is_featured'   => (bool) $l->is_featured,
            'published_at'  => $l->published_at?->toISOString(),
        ];
    }

    private function detail(MarketListing $l): array
    {
        return array_merge($this->card($l), [
            'description_ar' => $l->description_ar,
            'description_en' => $l->description_en,
            'images'         => $l->imageUrls(),
            'year'           => $l->year,
            'mileage'        => $l->mileage,
            'specs'          => $l->specs,
            'brand'          => $l->brand ? ['name_ar' => $l->brand->name_ar, 'slug' => $l->brand->slug] : null,
            'car_model'      => $l->carModel?->name_ar,
            'category'       => $l->category?->name_ar,
            'views_count'    => $l->views_count,
            'contact'        => [
                'name'     => $l->contact_name,
                'phone'    => $l->contact_phone,
                'whatsapp' => $l->contact_whatsapp,
                'telegram' => $l->contact_telegram,
            ],
        ]);
    }
}
