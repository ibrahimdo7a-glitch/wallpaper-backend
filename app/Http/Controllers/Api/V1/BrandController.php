<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::active()
            ->when($request->boolean('featured'), fn($q) => $q->featured())
            ->withCount(['carModels', 'wallpapers', 'apps'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn($b) => [
                'id'               => $b->id,
                'name_ar'          => $b->name_ar,
                'name_en'          => $b->name_en,
                'slug'             => $b->slug,
                'logo_url'         => $b->logo_url,
                'cover_image_url'  => $b->cover_image_url,
                'description_ar'   => $b->description_ar,
                'description_en'   => $b->description_en,
                'country'          => $b->country,
                'models_count'     => $b->models_count,
                'wallpapers_count' => $b->wallpapers_count,
                'apps_count'       => $b->apps_count,
                'is_featured'      => $b->is_featured,
            ]);

        return response()->json(['data' => $brands]);
    }

    public function show(string $slug)
    {
        $brand = Brand::active()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id'               => $brand->id,
                'name_ar'          => $brand->name_ar,
                'name_en'          => $brand->name_en,
                'slug'             => $brand->slug,
                'logo_url'         => $brand->logo_url,
                'cover_image_url'  => $brand->cover_image_url,
                'description_ar'   => $brand->description_ar,
                'description_en'   => $brand->description_en,
                'country'          => $brand->country,
                'website_url'      => $brand->website_url,
                'models_count'     => $brand->models_count,
                'wallpapers_count' => $brand->wallpapers_count,
                'apps_count'       => $brand->apps_count,
                'is_featured'      => $brand->is_featured,
                'meta_title'       => $brand->meta_title,
                'meta_description' => $brand->meta_description,
            ],
        ]);
    }

    public function models(string $slug, Request $request)
    {
        $brand = Brand::active()->where('slug', $slug)->firstOrFail();

        $models = $brand->carModels()
            ->where('is_active', true)
            ->when($request->get('car_type'), fn($q, $v) => $q->where('car_type', $v))
            ->when($request->get('fuel_type'), fn($q, $v) => $q->where('fuel_type', $v))
            ->orderBy('sort_order')
            ->get()
            ->map(fn($m) => [
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
                'wallpapers_count' => $m->wallpapers_count,
                'apps_count'       => $m->apps_count,
                'tutorials_count'  => $m->tutorials_count,
                'is_featured'      => $m->is_featured,
            ]);

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'name_en' => $brand->name_en, 'slug' => $brand->slug],
            'data'  => $models,
        ]);
    }
}
