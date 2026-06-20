<?php

namespace App\Filament\Resources\WatermarkResource\Pages;

use App\Filament\Resources\WatermarkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWatermark extends CreateRecord
{
    protected static string $resource = WatermarkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
