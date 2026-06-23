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

        // Default ALL Filament uploads to the app's storage disk (R2) with private
        // visibility. Two reasons:
        //  1. Fields without an explicit ->disk() otherwise upload to Filament's
        //     default disk (local) while the model URL accessors read from R2 → 404.
        //  2. Cloudflare R2 rejects ACLs, so public visibility fails silently
        //     (throw=false). Private objects are still served via the R2 public URL.
        FileUpload::configureUsing(function (FileUpload $upload) {
            $upload->disk(config('filesystems.default', 'public'))->visibility('private');
        });
    }
}
