<?php

use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HomepageController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\TelegramAuthController;
use App\Http\Controllers\Api\V1\UploaderController;
use App\Http\Controllers\Api\V1\WallpaperController;
use App\Http\Controllers\Api\V1\WallpaperUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:api', App\Http\Middleware\SetLocale::class])->group(function () {

    // Auth (admin)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // Member auth (Telegram login)
    Route::post('/auth/telegram/start', [TelegramAuthController::class, 'start'])->middleware('throttle:60,1');
    Route::get('/auth/telegram/status', [TelegramAuthController::class, 'status'])->middleware('throttle:240,1');
    Route::get('/member/me', [TelegramAuthController::class, 'me'])->middleware('auth:member');
    Route::post('/member/logout', [TelegramAuthController::class, 'logout'])->middleware('auth:member');
    Route::post('/member/notice/ack', [TelegramAuthController::class, 'dismissNotice'])->middleware('auth:member');

    // Member listings (submit + own list)
    Route::get('/member/listings', [\App\Http\Controllers\Api\V1\MemberListingController::class, 'mine'])->middleware('auth:member');
    Route::get('/member/listings/{id}', [\App\Http\Controllers\Api\V1\MemberListingController::class, 'show'])->middleware('auth:member');
    Route::post('/member/listings', [\App\Http\Controllers\Api\V1\MemberListingController::class, 'store'])->middleware(['auth:member', 'throttle:10,1']);
    Route::post('/member/listings/{id}', [\App\Http\Controllers\Api\V1\MemberListingController::class, 'update'])->middleware(['auth:member', 'throttle:10,1']);
    Route::post('/member/listings/{id}/action', [\App\Http\Controllers\Api\V1\MemberListingController::class, 'action'])->middleware(['auth:member', 'throttle:30,1']);
    Route::post('/contact', [\App\Http\Controllers\Api\V1\ContactController::class, 'send'])->middleware(['auth:member', 'throttle:5,1']);

    // Member saves + preferences
    Route::post('/member/saves/toggle', [\App\Http\Controllers\Api\V1\MemberController::class, 'toggleSave'])->middleware(['auth:member', 'throttle:60,1']);
    Route::get('/member/saved-ids', [\App\Http\Controllers\Api\V1\MemberController::class, 'savedIds'])->middleware('auth:member');
    Route::get('/member/saves', [\App\Http\Controllers\Api\V1\MemberController::class, 'mySaves'])->middleware('auth:member');
    Route::post('/member/prefs', [\App\Http\Controllers\Api\V1\MemberController::class, 'updatePrefs'])->middleware('auth:member');

    // Public wallpapers
    Route::get('/wallpapers', [WallpaperController::class, 'index']);
    Route::get('/wallpapers/search', [WallpaperController::class, 'search']);
    Route::get('/wallpapers/{slug}', [WallpaperController::class, 'show']);
    Route::post('/wallpapers/{id}/like', [WallpaperController::class, 'like'])->middleware('throttle:30,1');
    Route::post('/wallpapers/{id}/download', [WallpaperController::class, 'download'])->middleware(['auth:member', 'throttle:10,1']);
    Route::post('/wallpapers/{id}/report', [WallpaperController::class, 'report'])->middleware('throttle:5,1');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    // Homepage & Navigation
    Route::get('/homepage', [HomepageController::class, 'index']);
    Route::get('/navigation', [HomepageController::class, 'navigation']);
    Route::get('/search', [HomepageController::class, 'search']);
    Route::post('/track/visit', [HomepageController::class, 'trackVisit'])->middleware('throttle:60,1');
    // First-party analytics beacon (pageviews + presence heartbeats).
    Route::post('/track/hit', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'track'])->middleware('throttle:300,1');

    // Marketplace (السوق)
    Route::get('/market', [\App\Http\Controllers\Api\V1\MarketController::class, 'index']);
    Route::get('/market/config', [\App\Http\Controllers\Api\V1\MarketController::class, 'config']);
    Route::get('/market-categories', [\App\Http\Controllers\Api\V1\MarketController::class, 'categories']);
    Route::post('/market/{id}/view', [\App\Http\Controllers\Api\V1\MarketController::class, 'view'])->middleware('throttle:60,1');
    Route::get('/market/{id}/phone', [\App\Http\Controllers\Api\V1\MarketController::class, 'phone'])->whereNumber('id')->middleware('throttle:20,1');
    Route::get('/market/{slug}', [\App\Http\Controllers\Api\V1\MarketController::class, 'show']);

    // Content item detail + actions
    Route::get('/content/{id}', [\App\Http\Controllers\Api\V1\ContentController::class, 'show']);
    Route::post('/content/{id}/view', [\App\Http\Controllers\Api\V1\ContentController::class, 'view'])->middleware('throttle:60,1');
    Route::post('/content/{id}/like', [\App\Http\Controllers\Api\V1\ContentController::class, 'like'])->middleware('throttle:30,1');
    Route::post('/content/{id}/download', [\App\Http\Controllers\Api\V1\ContentController::class, 'download'])->middleware(['auth:member', 'throttle:20,1']);

    // Brands — dynamic Brand Builder system
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{slug}', [BrandController::class, 'show']);
    Route::get('/brands/{slug}/apps', [BrandController::class, 'apps']);
    Route::get('/brands/{slug}/showcase', [BrandController::class, 'showcase']);
    Route::get('/brands/{slug}/sections', [BrandController::class, 'sections']);
    Route::get('/brands/{slug}/sections/{sectionSlug}', [BrandController::class, 'sectionContent']);
    Route::get('/brands/{slug}/models', [BrandController::class, 'models']);
    Route::get('/brands/{slug}/models/{modelSlug}', [BrandController::class, 'modelShow']);
    Route::get('/brands/{slug}/models/{modelSlug}/sections/{sectionSlug}', [BrandController::class, 'modelSectionContent']);

    // News
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/categories', [NewsController::class, 'categories']);
    Route::get('/news/{slug}', [NewsController::class, 'show']);
    Route::post('/news/{id}/like', [NewsController::class, 'like'])->middleware('throttle:30,1');
    Route::post('/news/subscribe', [NewsController::class, 'subscribe'])->middleware('throttle:5,1');
    Route::get('/news/unsubscribe/{token}', [NewsController::class, 'unsubscribe']);

    // Apps
    Route::get('/app-categories', [AppController::class, 'categories']);
    Route::get('/apps', [AppController::class, 'index']);
    Route::get('/apps/{slug}', [AppController::class, 'show']);
    Route::post('/apps/{id}/download', [AppController::class, 'download'])->middleware(['auth:member', 'throttle:20,1']);

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
            'search_enabled',
            'search_placeholder_ar', 'search_placeholder_en',
            'popular_tags_ar', 'popular_tags_en',
            'feature_car_ar', 'feature_car_en',
            'feature_quality_ar', 'feature_quality_en',
            'feature_fast_ar', 'feature_fast_en',
            'footer_copyright_ar', 'footer_copyright_en',
            'terms_ar', 'terms_en',
            'privacy_ar', 'privacy_en',
            'about_ar', 'about_en',
            'contact_enabled',
            'ilink_enabled',
            'ilink_label_ar', 'ilink_label_en',
            'ilink_tooltip_ar', 'ilink_tooltip_en',
            'ilink_file_path',
            'site_favicon_path',
            'seo_google_verification', 'seo_bing_verification',
            'seo_keywords_ar', 'seo_keywords_en',
        ];
        $settings = collect($keys)->mapWithKeys(fn($k) => [$k => \App\Models\Setting::get($k, '')]);

        // search defaults to enabled when no setting exists yet
        $settings['search_enabled'] = $settings['search_enabled'] === ''
            ? true
            : filter_var($settings['search_enabled'], FILTER_VALIDATE_BOOLEAN);

        $settings['contact_enabled'] = $settings['contact_enabled'] === ''
            ? true
            : filter_var($settings['contact_enabled'], FILTER_VALIDATE_BOOLEAN);

        // Parse popular_tags as arrays
        $settings['popular_tags_ar'] = array_filter(array_map('trim', explode(',', $settings['popular_tags_ar'] ?? '')));
        $settings['popular_tags_en'] = array_filter(array_map('trim', explode(',', $settings['popular_tags_en'] ?? '')));

        // Build iLink file URL from stored path (legacy header banner; kept for backward compat)
        $filePath = $settings['ilink_file_path'] ?? '';
        $settings['ilink_file_url'] = $filePath
            ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.default', 'public'))->url($filePath)
            : '';
        unset($settings['ilink_file_path']);

        // Build the favicon URL (browser-tab / mobile icon) from the uploaded path.
        $faviconPath = $settings['site_favicon_path'] ?? '';
        $settings['favicon_url'] = $faviconPath
            ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.default', 'public'))->url($faviconPath)
            : '';
        unset($settings['site_favicon_path']);

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

// Telegram bot webhook — public (no locale/auth), secret in the path. Receives /start updates.
Route::post('/telegram/webhook/{secret}', [TelegramAuthController::class, 'webhook']);
