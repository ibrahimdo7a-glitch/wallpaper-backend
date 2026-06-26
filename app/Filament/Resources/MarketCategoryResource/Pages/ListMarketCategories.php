<?php

namespace App\Filament\Resources\MarketCategoryResource\Pages;

use App\Filament\Resources\MarketCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMarketCategories extends ListRecords
{
    protected static string $resource = MarketCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
