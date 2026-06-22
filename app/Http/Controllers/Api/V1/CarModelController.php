<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    public function show(string $brandSlug, string $modelSlug)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();

        $model = $brand->carModels()
            ->where('slug', $modelSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id'               => $model->id,
                'name_ar'          => $model->name_ar,
                'name_en'          => $model->name_en,
                'slug'             => $model->slug,
                'description_ar'   => $model->description_ar,
                'description_en'   => $model->description_en,
                'image_url'        => $model->image_url,
                'cover_image_url'  => $model->cover_image_url,
                'car_type'         => $model->car_type,
                'fuel_type'        => $model->fuel_type,
                'year_from'        => $model->year_from,
                'year_to'          => $model->year_to,
                'year_label'       => $model->year_label,
                'wallpapers_count' => $model->wallpapers_count,
                'apps_count'       => $model->apps_count,
                'tutorials_count'  => $model->tutorials_count,
                'is_featured'      => $model->is_featured,
                'brand' => [
                    'name_ar' => $brand->name_ar,
                    'name_en' => $brand->name_en,
                    'slug'    => $brand->slug,
                    'logo_url' => $brand->logo_url,
                ],
                'meta_title'       => $model->meta_title,
                'meta_description' => $model->meta_description,
            ],
        ]);
    }

    public function wallpapers(string $brandSlug, string $modelSlug, Request $request)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        $wallpapers = $model->wallpapers()
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'slug' => $brand->slug],
            'model' => ['name_ar' => $model->name_ar, 'slug' => $model->slug],
            'data'  => $wallpapers->items(),
            'meta'  => [
                'current_page' => $wallpapers->currentPage(),
                'last_page'    => $wallpapers->lastPage(),
                'per_page'     => $wallpapers->perPage(),
                'total'        => $wallpapers->total(),
            ],
        ]);
    }

    public function apps(string $brandSlug, string $modelSlug, Request $request)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        $apps = $model->apps()
            ->published()
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'slug' => $brand->slug],
            'model' => ['name_ar' => $model->name_ar, 'slug' => $model->slug],
            'data'  => $apps->map(fn($a) => [
                'id'            => $a->id,
                'title_ar'      => $a->title_ar,
                'title_en'      => $a->title_en,
                'slug'          => $a->slug,
                'icon_url'      => $a->icon_url,
                'version'       => $a->version,
                'safety_status' => $a->safety_status,
                'safety_badge'  => $a->safety_badge,
                'is_free'       => $a->is_free,
                'downloads_count' => $a->downloads_count,
                'category'      => $a->category ? ['name_ar' => $a->category->name_ar] : null,
            ]),
            'meta' => [
                'current_page' => $apps->currentPage(),
                'last_page'    => $apps->lastPage(),
                'total'        => $apps->total(),
            ],
        ]);
    }

    public function importantApps(string $brandSlug, string $modelSlug)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        $apps = $model->importantApps()
            ->published()
            ->with('category')
            ->orderBy('car_model_important_apps.sort_order')
            ->get()
            ->map(fn($a) => [
                'id'             => $a->id,
                'title_ar'       => $a->title_ar,
                'title_en'       => $a->title_en,
                'slug'           => $a->slug,
                'icon_url'       => $a->icon_url,
                'short_description_ar' => $a->short_description_ar,
                'short_description_en' => $a->short_description_en,
                'version'        => $a->version,
                'safety_status'  => $a->safety_status,
                'safety_badge'   => $a->safety_badge,
                'is_free'        => $a->is_free,
                'downloads_count' => $a->downloads_count,
            ]);

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'slug' => $brand->slug],
            'model' => ['name_ar' => $model->name_ar, 'slug' => $model->slug],
            'data'  => $apps,
        ]);
    }

    public function tutorials(string $brandSlug, string $modelSlug, Request $request)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        $tutorials = $model->tutorials()
            ->published()
            ->orderByDesc('published_at')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'slug' => $brand->slug],
            'model' => ['name_ar' => $model->name_ar, 'slug' => $model->slug],
            'data'  => $tutorials->map(fn($t) => [
                'id'           => $t->id,
                'title_ar'     => $t->title_ar,
                'title_en'     => $t->title_en,
                'slug'         => $t->slug,
                'summary_ar'   => $t->summary_ar,
                'summary_en'   => $t->summary_en,
                'cover_image_url' => $t->cover_image_url,
                'difficulty'   => $t->difficulty,
                'duration_label' => $t->duration_label,
                'video_url'    => $t->video_url,
                'views_count'  => $t->views_count,
                'published_at' => $t->published_at,
            ]),
            'meta' => ['current_page' => $tutorials->currentPage(), 'last_page' => $tutorials->lastPage(), 'total' => $tutorials->total()],
        ]);
    }

    public function files(string $brandSlug, string $modelSlug, Request $request)
    {
        $brand = Brand::active()->where('slug', $brandSlug)->firstOrFail();
        $model = $brand->carModels()->where('slug', $modelSlug)->where('is_active', true)->firstOrFail();

        $files = $model->carFiles()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'brand' => ['name_ar' => $brand->name_ar, 'slug' => $brand->slug],
            'model' => ['name_ar' => $model->name_ar, 'slug' => $model->slug],
            'data'  => $files->map(fn($f) => [
                'id'              => $f->id,
                'title_ar'        => $f->title_ar,
                'title_en'        => $f->title_en,
                'slug'            => $f->slug,
                'description_ar'  => $f->description_ar,
                'description_en'  => $f->description_en,
                'file_type'       => $f->file_type,
                'version'         => $f->version,
                'file_size'       => $f->file_size,
                'file_size_label' => $f->file_size_label,
                'downloads_count' => $f->downloads_count,
                'published_at'    => $f->published_at,
            ]),
            'meta' => ['current_page' => $files->currentPage(), 'last_page' => $files->lastPage(), 'total' => $files->total()],
        ]);
    }
}
