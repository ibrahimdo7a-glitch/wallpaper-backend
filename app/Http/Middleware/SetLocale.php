<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $request->header('Accept-Language', 'ar');
        $locale = str_contains($locale, 'ar') ? 'ar' : 'en';

        app()->setLocale($locale);

        return $next($request);
    }
}
