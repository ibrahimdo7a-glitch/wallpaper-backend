<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Temporary debug route
Route::get('/debug-error', function () {
    try {
        // Try to instantiate the problematic resource
        $user = \App\Models\User::first();
        if (!$user) return 'No users found in DB';

        auth()->login($user);

        $panel = \Filament\Facades\Filament::getDefaultPanel();

        // Try building the form
        $resource = \App\Filament\Resources\WallpaperResource::class;
        $form = \Filament\Forms\Form::make(
            new class extends \Livewire\Component {
                public function render() { return ''; }
            }
        )->model(\App\Models\Wallpaper::class);

        $resource::form($form);

        return response()->json(['status' => 'OK - form loaded successfully']);

    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'type'  => get_class($e),
            'file'  => str_replace(base_path(), '', $e->getFile()),
            'line'  => $e->getLine(),
            'trace' => collect(explode("\n", $e->getTraceAsString()))
                ->take(15)
                ->map(fn($l) => str_replace(base_path(), '', $l))
                ->values(),
        ], 200);
    }
});
