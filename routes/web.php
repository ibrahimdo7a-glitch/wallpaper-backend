<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Temporary debug route - shows last Laravel error
Route::get('/debug-last-error', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) return 'No log file found';
    $content = file_get_contents($logFile);
    // Get last 5000 chars
    $tail = substr($content, -5000);
    return response('<pre>' . htmlspecialchars($tail) . '</pre>');
});
