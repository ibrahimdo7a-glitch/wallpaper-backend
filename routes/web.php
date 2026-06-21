<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/debug-error', function () {
    $results = [];
    try {
        $disk = config('filesystems.default');
        $results['filesystem_disk'] = $disk;
        \Storage::disk($disk)->exists('test');
        $results['filesystem_connection'] = 'OK';
    } catch (\Throwable $e) {
        $results['filesystem_connection'] = 'FAIL: ' . $e->getMessage();
    }
    try {
        class_exists(\App\Filament\Resources\WallpaperResource::class);
        $results['WallpaperResource'] = 'OK';
    } catch (\Throwable $e) {
        $results['WallpaperResource'] = 'FAIL: ' . $e->getMessage();
    }
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
