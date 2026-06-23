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

        // Filament uploads on Cloudflare R2:
        //  - visibility('private'): R2 rejects ACLs, public visibility fails silently.
        //    Files are still served via the R2 public bucket URL.
        //  - fetchFileInformation(false): the metadata request Filament makes to read
        //    an existing file's size is what hangs forever on "Waiting for size" with
        //    R2 (no CORS on the signed endpoint). Disabling it fixes the stuck preview
        //    and restores the remove/replace controls on edit forms.
        FileUpload::configureUsing(function (FileUpload $upload) {
            $upload->visibility('private')->fetchFileInformation(false);
        });
    }
}
