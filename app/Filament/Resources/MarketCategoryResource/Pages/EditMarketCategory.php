<?php

namespace App\Filament\Resources\MarketCategoryResource\Pages;

use App\Filament\Resources\MarketCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketCategory extends EditRecord
{
    protected static string $resource = MarketCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
