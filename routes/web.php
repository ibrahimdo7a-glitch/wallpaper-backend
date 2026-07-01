<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// SEO: crawlers reach these at qev.app/{sitemap.xml,robots.txt} via the frontend rewrite.
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'sitemap']);
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots']);

// Admin panel Telegram-OTP second factor (guest-accessible; challenge lives in the session).
// Kept off the /admin prefix so it never collides with Filament's panel routing.
Route::get('/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'show'])->middleware('throttle:30,1');
Route::post('/two-factor', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->middleware('throttle:20,1');

// Serve uploaded files directly (fallback when storage symlink is unreliable in Docker)
Route::get('/storage/{path}', function (string $path) {
    $filePath = storage_path('app/public/' . $path);

    if (! file_exists($filePath) || ! is_file($filePath)) {
        abort(404);
    }

    $mime = mime_content_type($filePath) ?: 'application/octet-stream';

    return response()->file($filePath, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*');
