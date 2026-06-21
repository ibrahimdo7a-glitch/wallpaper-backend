<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/debug-error', function () {
    $results = [
        'env_FILESYSTEM_DISK' => env('FILESYSTEM_DISK', 'NOT_SET'),
        'config_disk' => config('filesystems.default'),
        'disk_test' => (function() {
            try { \Storage::disk(config('filesystems.default'))->exists('test'); return 'OK'; }
            catch (\Throwable $e) { return 'FAIL: ' . $e->getMessage(); }
        })(),
        'watermark_scope' => (function() {
            try { \App\Models\Watermark::query()->where('is_active', true)->count(); return 'OK'; }
            catch (\Throwable $e) { return 'FAIL: ' . $e->getMessage(); }
        })(),
        'tags_table' => (function() {
            try { \DB::table('tags')->count(); return 'OK'; }
            catch (\Throwable $e) { return 'FAIL: ' . $e->getMessage(); }
        })(),
        'categories_table' => (function() {
            try { return 'OK: count=' . \DB::table('categories')->count(); }
            catch (\Throwable $e) { return 'FAIL: ' . $e->getMessage(); }
        })(),
        'wallpaper_resource_load' => (function() {
            try {
                $class = \App\Filament\Resources\WallpaperResource::class;
                class_exists($class);
                return 'OK';
            } catch (\Throwable $e) { return 'FAIL: ' . $e->getMessage(); }
        })(),
        'last_log' => (function() {
            $file = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
            if (!file_exists($file)) return 'no log file';
            $lines = file($file);
            $last = array_slice($lines, -30);
            return implode('', $last);
        })(),
    ];
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
