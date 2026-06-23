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

        // Cloudflare R2 does not support ACLs — uploading with public visibility
        // fails silently. Default all Filament uploads to private; files stay
        // publicly served via the R2 public bucket URL.
        FileUpload::configureUsing(function (FileUpload $upload) {
            $upload->visibility('private');
        });
    }
}
