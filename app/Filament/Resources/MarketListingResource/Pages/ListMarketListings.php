<?php

namespace App\Filament\Resources\MarketListingResource\Pages;

use App\Filament\Resources\MarketListingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketListings extends ListRecords
{
    protected static string $resource = MarketListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
