<?php

namespace App\Filament\Resources\WallpaperResource\Pages;

use App\Filament\Resources\WallpaperResource;
use App\Jobs\ProcessWallpaperImage;
use Filament\Resources\Pages\CreateRecord;

class CreateWallpaper extends CreateRecord
{
    protected static string $resource = WallpaperResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        ProcessWallpaperImage::dispatch($this->record->id)->onQueue('image-processing');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();
        $data['status'] = auth()->user()->auto_publish ? 'published' : 'pending';

        return $data;
    }
}
