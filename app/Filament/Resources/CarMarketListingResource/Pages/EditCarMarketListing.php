<?php

namespace App\Filament\Resources\CarMarketListingResource\Pages;

use App\Filament\Resources\CarMarketListingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCarMarketListing extends EditRecord
{
    protected static string $resource = CarMarketListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
