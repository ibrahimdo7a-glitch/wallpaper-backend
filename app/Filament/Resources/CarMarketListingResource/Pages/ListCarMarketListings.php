<?php

namespace App\Filament\Resources\CarMarketListingResource\Pages;

use App\Filament\Resources\CarMarketListingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCarMarketListings extends ListRecords
{
    protected static string $resource = CarMarketListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
