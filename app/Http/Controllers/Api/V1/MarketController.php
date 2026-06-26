<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\MarketCategory;
use App\Models\MarketField;
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
        $carFields = MarketField::where('scope', 'cars')->where('is_enabled', true)
            ->orderBy('sort_order')->get()->map(fn ($f) => $this->fieldDef($f))->values();

        $sections = MarketCategory::active()->get()->map(fn ($c) => [
            'id'       => $c->id,
            'slug'     => $c->slug,
            'name_ar'  => $c->name_ar,
            'name_en'  => $c->name_en,
            'icon'     => $c->icon,
        ])->values();

        return response()->json([
            'cars' => [
                'enabled'  => $this->sectionEnabled('cars'),
                'label_ar' => Setting::get('cars_label_ar') ?: 'سوق السيارات',
                'label_en' => Setting::get('cars_label_en') ?: 'Cars',
                'fields'   => $carFields,
            ],
            'parts' => [
                'enabled'  => $this->sectionEnabled('parts'),
                'label_ar' => Setting::get('parts_label_ar') ?: 'قطع وأكسسوارات',
                'label_en' => Setting::get('parts_label_en') ?: 'Parts & Accessories',
                'sections' => $sections,
            ],
        ]);
    }

    private function fieldDef(MarketField $f): array
    {
        return [
            'key'           => $f->key,
            'label_ar'      => $f->label_ar,
            'label_en'      => $f->label_en,
            'type'          => $f->type,
            'unit'          => $f->unit,
            'options'       => $f->options,
            'is_filterable' => (bool) $f->is_filterable,
        ];
    }

    /** Resolve a listing's enabled fields into a labelled, ordered list for display. */
    private function resolveSpecFields(MarketListing $l): array
    {
        $scope = in_array($l->listing_type, ['car_sale', 'car_request'], true) ? 'cars' : 'parts';
        $fields = MarketField::forContext($scope, $scope === 'parts' ? $l->market_category_id : null)
            ->where('is_enabled', true)->get();

        $out = [];
        foreach ($fields as $f) {
            $value = $f->isColumn() ? ($l->{$f->column_name} ?? null) : ($l->specs[$f->key] ?? null);
            if ($value === null || $value === '') {
                continue;
            }
            if ($f->type === 'select') {
                $value = $f->optionsMap()[$value] ?? $value;
            } elseif ($f->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'نعم' : 'لا';
            }
            $out[] = [
                'key'   => $f->key,
                'label' => $f->label_ar,
                'unit'  => $f->unit,
                'type'  => $f->type,
                'value' => $value,
            ];
        }
        return $out;
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

    // GET /v1/market/{id}/phone  (revealed only after the human check; throttled)
    public function phone(int $id): JsonResponse
    {
        $l = MarketListing::published()->where('id', $id)->first(['contact_phone', 'country']);
        if (! $l || ! $l->contact_phone) {
            return response()->json(['number' => null]);
        }

        $codes = ['قطر' => '974', 'السعودية' => '966', 'الإمارات' => '971', 'الكويت' => '965', 'البحرين' => '973', 'عُمان' => '968'];
        $code  = $codes[$l->country] ?? '974';
        $local = preg_replace('/\D/', '', $l->contact_phone);
        if (str_starts_with($local, $code)) {
            $local = substr($local, strlen($code));   // admin already typed the code
        }

        return response()->json([
            'dial_code' => '+' . $code,
            'number'    => $local,
            'tel'       => '+' . $code . $local,
        ]);
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
            'spec_fields'    => $this->resolveSpecFields($l),
            'brand'          => $l->brand ? ['name_ar' => $l->brand->name_ar, 'slug' => $l->brand->slug] : null,
            'car_model'      => $l->carModel?->name_ar,
            'category'       => $l->category?->name_ar,
            'views_count'    => $l->views_count,
            'contact'        => [
                'name'      => $l->contact_name,
                // Raw phone is never sent in the page payload — revealed only via
                // /market/{id}/phone after the human check (anti-scraping).
                'has_phone' => filled($l->contact_phone),
                'whatsapp'  => $l->contact_whatsapp,
                'telegram'  => $l->contact_telegram,
            ],
        ]);
    }
}
