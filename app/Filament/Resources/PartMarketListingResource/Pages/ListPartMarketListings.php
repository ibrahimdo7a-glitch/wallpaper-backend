<?php

namespace App\Filament\Resources\PartMarketListingResource\Pages;

use App\Filament\Resources\PartMarketListingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartMarketListings extends ListRecords
{
    protected static string $resource = PartMarketListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
