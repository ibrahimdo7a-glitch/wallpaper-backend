<?php

namespace App\Filament\Resources\WatermarkResource\Pages;

use App\Filament\Resources\WatermarkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWatermarks extends ListRecords
{
    protected static string $resource = WatermarkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('إضافة توقيع')];
    }
}
