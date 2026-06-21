<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Temporary debug route
Route::get('/debug-error', function () {
    $results = [];
    try {
        // 1. Check DB connection
        \DB::connection()->getPdo();
        $results['db'] = 'OK';
    } catch (\Throwable $e) {
        $results['db'] = 'FAIL: ' . $e->getMessage();
    }

    try {
        // 2. Check tables exist
        $results['wallpapers_table'] = \Schema::hasTable('wallpapers') ? 'exists' : 'MISSING';
        $results['categories_table'] = \Schema::hasTable('categories') ? 'exists' : 'MISSING';
        $results['permissions_table'] = \Schema::hasTable('permissions') ? 'exists' : 'MISSING';
    } catch (\Throwable $e) {
        $results['tables'] = 'FAIL: ' . $e->getMessage();
    }

    try {
        // 3. Check filesystem disk
        $disk = config('filesystems.default');
        $results['filesystem_disk'] = $disk;
        \Storage::disk($disk)->exists('test');
        $results['filesystem_connection'] = 'OK';
    } catch (\Throwable $e) {
        $results['filesystem_connection'] = 'FAIL: ' . $e->getMessage();
    }

    try {
        // 4. Check BadgeColumn exists
        $results['BadgeColumn'] = class_exists(\Filament\Tables\Columns\BadgeColumn::class) ? 'exists' : 'MISSING';
    } catch (\Throwable $e) {
        $results['BadgeColumn'] = 'ERROR: ' . $e->getMessage();
    }

    try {
        // 5. Check WallpaperResource class loads
        class_exists(\App\Filament\Resources\WallpaperResource::class);
        $results['WallpaperResource'] = 'loaded OK';
    } catch (\Throwable $e) {
        $results['WallpaperResource'] = 'FAIL: ' . $e->getMessage() . ' in ' . str_replace(base_path(), '', $e->getFile()) . ':' . $e->getLine();
    }

    try {
        // 6. Check user + permissions
        $user = \App\Models\User::first();
        $results['first_user'] = $user ? $user->email : 'NO USERS';
        if ($user) {
            $results['user_permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
        }
    } catch (\Throwable $e) {
        $results['user_check'] = 'FAIL: ' . $e->getMessage();
    }

    return response()->json($results, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
});
