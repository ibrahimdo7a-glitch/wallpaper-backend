<?php

namespace App\Filament\Resources\WatermarkResource\Pages;

use App\Filament\Resources\WatermarkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWatermark extends EditRecord
{
    protected static string $resource = WatermarkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
