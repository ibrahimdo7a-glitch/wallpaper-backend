<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\UploaderController;
use App\Http\Controllers\Api\V1\WallpaperController;
use App\Http\Controllers\Api\V1\WallpaperUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:api', App\Http\Middleware\SetLocale::class])->group(function () {

    // Auth
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Public wallpapers
    Route::get('/wallpapers', [WallpaperController::class, 'index']);
    Route::get('/wallpapers/search', [WallpaperController::class, 'search']);
    Route::get('/wallpapers/{slug}', [WallpaperController::class, 'show']);
    Route::post('/wallpapers/{id}/like', [WallpaperController::class, 'like'])->middleware('throttle:30,1');
    Route::post('/wallpapers/{id}/download', [WallpaperController::class, 'download'])->middleware('throttle:10,1');
    Route::post('/wallpapers/{id}/report', [WallpaperController::class, 'report'])->middleware('throttle:5,1');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    // Uploaders
    Route::get('/uploaders/{username}', [UploaderController::class, 'show']);
    Route::get('/uploaders/{username}/wallpapers', [UploaderController::class, 'wallpapers']);

    // Translations
    Route::get('/translations', function () {
        return response()->json([
            'data' => \App\Models\Translation::all()->groupBy('group'),
        ]);
    });

    // Public settings
    Route::get('/settings/public', function () {
        $keys = ['site_name_ar', 'site_name_en', 'logo', 'default_language', 'likes_enabled', 'downloads_enabled', 'reports_enabled', 'watermark_enabled'];
        return response()->json([
            'data' => collect($keys)->mapWithKeys(fn($k) => [$k => \App\Models\Setting::get($k)]),
        ]);
    });

    // Site content settings (for frontend dynamic content)
    Route::get('/settings/site-content', function () {
        $keys = [
            'site_name_ar', 'site_name_en',
            'hero_title_ar', 'hero_title_en',
            'hero_subtitle_ar', 'hero_subtitle_en',
            'search_placeholder_ar', 'search_placeholder_en',
            'popular_tags_ar', 'popular_tags_en',
            'feature_car_ar', 'feature_car_en',
            'feature_quality_ar', 'feature_quality_en',
            'feature_fast_ar', 'feature_fast_en',
            'footer_copyright_ar', 'footer_copyright_en',
        ];
        $settings = collect($keys)->mapWithKeys(fn($k) => [$k => \App\Models\Setting::get($k, '')]);

        // Parse popular_tags as arrays
        $settings['popular_tags_ar'] = array_filter(array_map('trim', explode(',', $settings['popular_tags_ar'] ?? '')));
        $settings['popular_tags_en'] = array_filter(array_map('trim', explode(',', $settings['popular_tags_en'] ?? '')));

        return response()->json([
            'data' => $settings,
        ]);
    });

    // Ads
    Route::get('/ads/{position}', function (string $position) {
        $locale = request('locale', 'ar');
        $ads = \App\Models\Ad::where('position', $position)
            ->where('is_active', true)
            ->where(fn($q) => $q->where('language', $locale)->orWhere('language', 'both'))
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->get();
        return response()->json(['data' => $ads]);
    });

    // Tags
    Route::get('/tags', function () {
        return response()->json([
            'data' => \App\Models\Tag::orderByDesc('wallpapers_count')->limit(100)->get(),
        ]);
    });

    Route::get('/tags/{slug}', function (string $slug) {
        $tag = \App\Models\Tag::where('slug', $slug)->firstOrFail();
        return response()->json(['data' => $tag]);
    });

    // Authenticated endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', fn(Request $r) => response()->json(['data' => $r->user()->load('roles', 'permissions')]));

        Route::post('/wallpapers', [WallpaperUploadController::class, 'store']);

        Route::get('/my-wallpapers', function (Request $r) {
            return response()->json([
                'data' => $r->user()->wallpapers()
                    ->with('category', 'tags', 'watermark')
                    ->orderByDesc('created_at')
                    ->paginate(24),
            ]);
        });

        Route::get('/my-stats', function (Request $r) {
            $service = app(\App\Services\StatisticsService::class);
            return response()->json(['data' => $service->getUserStats($r->user()->id)]);
        });

        Route::get('/available-watermarks', function (Request $r) {
            $user = $r->user();
            $allowed = $user->allowedWatermarks;
            if ($allowed->isEmpty()) {
                $watermarks = \App\Models\Watermark::active()->get();
            } else {
                $watermarks = $allowed->where('is_active', true);
            }
            return response()->json(['data' => $watermarks]);
        });
    });
});
