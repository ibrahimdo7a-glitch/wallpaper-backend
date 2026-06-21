<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Cache::remember('categories.tree', 3600, function () {
            return Category::active()
                ->root()
                ->withCount(['wallpapers as wallpapers_count' => fn($q) => $q->where('status', 'published')])
                ->with(['children' => fn($q) => $q->withCount(['wallpapers as wallpapers_count' => fn($q2) => $q2->where('status', 'published')]), 'children.children'])
                ->orderBy('sort_order')
                ->get()
                ->map(fn($c) => $this->formatCategory($c));
        });

        return response()->json(['data' => $categories]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Cache::remember("category.{$slug}", 3600, function () use ($slug) {
            return Category::active()
                ->with(['parent', 'children'])
                ->where('slug', $slug)
                ->firstOrFail();
        });

        return response()->json([
            'data' => array_merge($this->formatCategory($category), [
                'description_ar' => $category->description_ar,
                'description_en' => $category->description_en,
                'cover_image_url' => $category->cover_image_url,
                'meta_title_ar' => $category->meta_title_ar,
                'meta_title_en' => $category->meta_title_en,
                'meta_description_ar' => $category->meta_description_ar,
                'meta_description_en' => $category->meta_description_en,
                'breadcrumbs' => $category->getBreadcrumbs(),
                'parent' => $category->parent ? $this->formatCategory($category->parent) : null,
                'children' => $category->children->map(fn($c) => $this->formatCategory($c)),
            ]),
        ]);
    }

    protected function formatCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'icon' => $category->icon,
            'cover_image_url' => $category->cover_image
                ? \Storage::disk(config('filesystems.default', 'public'))->url($category->cover_image)
                : null,
            'wallpapers_count' => $category->wallpapers_count,
            'downloads_count' => $category->downloads_count,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn($c) => $this->formatCategory($c))
                : [],
        ];
    }
}
