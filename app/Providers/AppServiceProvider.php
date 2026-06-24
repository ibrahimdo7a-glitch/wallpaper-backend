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

        // TEMP DIAGNOSTIC: show full errors on admin pages only (auth-gated). Remove after.
        if (request()->is('admin') || request()->is('admin/*')) {
            config(['app.debug' => true]);
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Filament uploads on Cloudflare R2 (applies to EVERY FileUpload):
        //  - disk(r2): fields without an explicit ->disk() otherwise upload to the
        //    default (local) disk while the model URL accessors read from R2 → 404.
        //    This was the cause of broken content/wallpaper thumbnails.
        //  - visibility('private'): R2 rejects ACLs; public visibility fails silently.
        //    Files are still served via the R2 public bucket URL.
        //  - fetchFileInformation(false): skips the metadata request that hangs on
        //    "Waiting for size" for existing files.
        FileUpload::configureUsing(function (FileUpload $upload) {
            $upload->disk(config('filesystems.default', 'public'))
                ->visibility('private')
                ->fetchFileInformation(false);
        });
    }
}
