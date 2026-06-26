<?php

namespace App\Filament\Resources\PartMarketListingResource\Pages;

use App\Filament\Resources\PartMarketListingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartMarketListing extends EditRecord
{
    protected static string $resource = PartMarketListingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
