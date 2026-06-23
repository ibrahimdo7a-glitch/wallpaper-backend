<?php

namespace App\Providers;

use Filament\Forms\Components\FileUpload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Force HTTPS in production (Railway serves behind HTTPS proxy)
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // R2 rejects ACLs, so public-visibility uploads fail silently. Default all
        // Filament uploads to private (still served via the R2 public bucket URL).
        // NOTE: we intentionally do NOT force ->disk() here — fields set their disk
        // explicitly, and forcing r2 globally triggered slow/hung r2 round-trips.
        FileUpload::configureUsing(function (FileUpload $upload) {
            $upload->visibility('private');
        });
    }
}
