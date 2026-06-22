<?php

namespace App\Filament\Resources\AndroidAppResource\Pages;

use App\Filament\Resources\AndroidAppResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAndroidApps extends ListRecords
{
    protected static string $resource = AndroidAppResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
