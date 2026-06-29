<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// SEO: crawlers reach these at qev.app/{sitemap.xml,robots.txt} via the frontend rewrite.
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'sitemap']);
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots']);

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
