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
        try {
            // Run synchronously (no queue worker in this deployment)
            ProcessWallpaperImage::dispatchSync($this->record->id);
        } catch (\Throwable $e) {
            \Log::warning("Image processing failed for wallpaper {$this->record->id}: " . $e->getMessage());
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();
        $data['status'] = auth()->user()->auto_publish ? 'published' : 'pending';

        return $data;
    }
}
