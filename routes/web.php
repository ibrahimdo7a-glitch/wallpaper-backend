<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/debug-upload', function () {
    $results = [];

    // Redis check
    try {
        \Cache::store('redis')->put('test', 'ok', 10);
        $results['redis'] = 'OK';
    } catch (\Throwable $e) {
        $results['redis'] = 'FAIL: ' . $e->getMessage();
    }

    // Session driver
    $results['session_driver'] = config('session.driver');

    // Livewire temp dirs
    $dirs = [
        storage_path('app/livewire-tmp'),
        storage_path('app/public/livewire-tmp'),
        storage_path('app/private/livewire-tmp'),
    ];
    foreach ($dirs as $dir) {
        $exists = is_dir($dir);
        $writable = $exists && is_writable($dir);
        $results['dir_' . basename(dirname($dir)) . '_livewire-tmp'] = $exists ? ($writable ? 'OK writable' : 'exists but NOT writable') : 'MISSING';
    }

    // Try writing to temp dir
    try {
        $path = storage_path('app/livewire-tmp/test-' . time() . '.txt');
        file_put_contents($path, 'test');
        unlink($path);
        $results['write_test'] = 'OK';
    } catch (\Throwable $e) {
        $results['write_test'] = 'FAIL: ' . $e->getMessage();
    }

    // Livewire disk config
    $results['livewire_disk'] = config('livewire.temporary_file_upload.disk', 'NOT SET');

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

