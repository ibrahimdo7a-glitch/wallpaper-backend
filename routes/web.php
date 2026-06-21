<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// TEMP DEBUG - remove after diagnosis
Route::get('/debug-log', function () {
    $log = storage_path('logs/laravel.log');
    if (!file_exists($log)) return 'no log file';
    $lines = array_slice(file($log), -80);
    return '<pre style="white-space:pre-wrap;word-break:break-all">' . htmlspecialchars(implode('', $lines)) . '</pre>';
});

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
