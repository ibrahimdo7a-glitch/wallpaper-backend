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
        'last_error' => (function() {
            $file = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
            if (!file_exists($file)) return 'no log file';
            $content = file_get_contents($file);
            // Find all ERROR entries
            preg_match_all('/\[\d{4}-\d{2}-\d{2}.*?\] \w+\.ERROR:.*?(?=\[\d{4}-\d{2}-\d{2}|\z)/s', $content, $matches);
            if (empty($matches[0])) return 'no errors found';
            $last = end($matches[0]);
            // Return first 2000 chars of last error
            return substr($last, 0, 2000);
        })(),
    ];
    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});
