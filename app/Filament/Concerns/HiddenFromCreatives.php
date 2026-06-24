<?php

namespace App\Filament\Concerns;

/**
 * Hides a Filament resource/page from the "مبدع" (creative) role, whose members
 * only manage wallpapers, sub-sections, models, designers and watermarks.
 * Every other panel user (super admin, etc.) is unaffected — this can never lock
 * out an admin, it only narrows what the creative role sees.
 */
trait HiddenFromCreatives
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ! $user->hasRole('مبدع');
    }
}
