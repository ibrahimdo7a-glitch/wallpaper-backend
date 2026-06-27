<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AndroidApp;
use App\Models\AppCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AppController extends Controller
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'public');
    }

    // GET /v1/apps
    public function index(Request $request): JsonResponse
    {
        $query = AndroidApp::published()
            ->with('category:id,name_ar,name_en,slug,icon')
            ->select([
                'id', 'app_category_id', 'title_ar', 'title_en', 'slug',
                'short_description_ar', 'short_description_en',
                'badge_text_ar', 'badge_text_en', 'works_on_car_screen',
                'icon_file', 'cover_image', 'version', 'developer', 'min_android',
                'file_size', 'is_free', 'is_featured',
                'downloads_count', 'published_at',
            ]);

        if ($request->category) {
            $catId = is_numeric($request->category)
                ? $request->category
                : AppCategory::where('slug', $request->category)->value('id');
            if ($catId) $query->where('app_category_id', $catId);
        }

        if ($request->search) {
            $q = $request->search;
            $query->where(fn($q2) =>
                $q2->where('title_ar', 'ILIKE', "%{$q}%")
                   ->orWhere('title_en', 'ILIKE', "%{$q}%")
                   ->orWhere('developer', 'ILIKE', "%{$q}%")
            );
        }

        if ($request->featured) {
            $query->where('is_featured', true);
        }

        $sort = match($request->sort) {
            'most_downloaded' => ['downloads_count', 'desc'],
            'oldest'          => ['published_at', 'asc'],
            default           => ['published_at', 'desc'],
        };
        // Featured apps always surface first (rendered as highlight boxes on the apps page).
        $query->orderByDesc('is_featured')->orderBy(...$sort);

        $apps = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $apps->map(fn($a) => $this->formatApp($a)),
            'meta' => [
                'current_page' => $apps->currentPage(),
                'last_page'    => $apps->lastPage(),
                'per_page'     => $apps->perPage(),
                'total'        => $apps->total(),
            ],
        ]);
    }

    // GET /v1/apps/{slug}
    public function show(string $slug): JsonResponse
    {
        $app = Cache::remember("app.{$slug}", 300, function () use ($slug) {
            return AndroidApp::published()
                ->with(['category:id,name_ar,name_en,slug,icon', 'installationSteps'])
                ->where('slug', $slug)
                ->firstOrFail();
        });

        return response()->json(['data' => $this->formatAppFull($app)]);
    }

    // POST /v1/apps/{id}/download
    public function download(int $id): JsonResponse
    {
        $app = AndroidApp::published()->findOrFail($id);
        $app->increment('downloads_count');

        $url = $app->apk_file
            ? Storage::disk($this->disk)->url($app->apk_file)
            : $app->external_url;

        return response()->json(['download_url' => $url]);
    }

    // GET /v1/app-categories
    public function categories(): JsonResponse
    {
        $cats = Cache::remember('app_categories', 300, function () {
            return AppCategory::active()
                ->withCount(['apps' => fn($q) => $q->where('status', 'published')])
                ->orderBy('sort_order')
                ->get(['id', 'name_ar', 'name_en', 'slug', 'icon', 'cover_image', 'apps_count']);
        });

        return response()->json([
            'data' => $cats->map(fn($c) => [
                'id'             => $c->id,
                'name_ar'        => $c->name_ar,
                'name_en'        => $c->name_en,
                'slug'           => $c->slug,
                'icon'           => $c->icon,
                'apps_count'     => $c->apps_count,
                'cover_image_url'=> $c->cover_image
                    ? Storage::disk($this->disk)->url($c->cover_image)
                    : null,
            ]),
        ]);
    }

    private function formatApp(AndroidApp $app): array
    {
        return [
            'id'             => $app->id,
            'title_ar'       => $app->title_ar,
            'title_en'       => $app->title_en,
            'slug'           => $app->slug,
            'short_description_ar' => $app->short_description_ar,
            'short_description_en' => $app->short_description_en,
            'badge_text_ar'  => $app->badge_text_ar,
            'badge_text_en'  => $app->badge_text_en,
            'works_on_car_screen' => (bool) $app->works_on_car_screen,
            'icon_url'       => $app->icon_file ? Storage::disk($this->disk)->url($app->icon_file) : null,
            'cover_image_url'=> $app->cover_image ? Storage::disk($this->disk)->url($app->cover_image) : null,
            'version'        => $app->version,
            'developer'      => $app->developer,
            'min_android'    => $app->min_android,
            'file_size_label'=> $app->file_size_label,
            'is_free'        => $app->is_free,
            'is_featured'    => $app->is_featured,
            'sort_order'     => $app->sort_order,
            'downloads_count'=> $app->downloads_count,
            'published_at'   => $app->published_at?->toISOString(),
            'category'       => $app->category ? [
                'id'      => $app->category->id,
                'name_ar' => $app->category->name_ar,
                'name_en' => $app->category->name_en,
                'slug'    => $app->category->slug,
                'icon'    => $app->category->icon,
            ] : null,
        ];
    }

    private function formatAppFull(AndroidApp $app): array
    {
        return array_merge($this->formatApp($app), [
            'description_ar'     => $app->description_ar,
            'description_en'     => $app->description_en,
            'package_name'       => $app->package_name,
            'has_apk'            => (bool) $app->apk_file,
            'has_external_url'   => (bool) $app->external_url,
            'installation_steps' => $app->installationSteps->map(fn($s) => [
                'step_number' => $s->step_number,
                'image_url'   => Storage::disk($this->disk)->url($s->image_file),
                'title_ar'    => $s->title_ar,
                'title_en'    => $s->title_en,
            ]),
        ]);
    }
}
