<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/debug-error', function () {
    return response()->json([
        'env_FILESYSTEM_DISK' => env('FILESYSTEM_DISK', 'NOT_SET'),
        'config_disk' => config('filesystems.default'),
        'disk_test' => (function() {
            try {
                \Storage::disk(config('filesystems.default'))->exists('test');
                return 'OK';
            } catch (\Throwable $e) {
                return 'FAIL: ' . $e->getMessage();
            }
        })(),
    ], 200, [], JSON_PRETTY_PRINT);
});
