<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Hides a relation-manager tab (e.g. tutorials / files inside a model) from the
 * "مبدع" creative role, who only works on wallpapers and sub-sections.
 */
trait RelationHiddenFromCreatives
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ! auth()->user()?->hasRole('مبدع');
    }
}
