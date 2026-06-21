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
            ProcessWallpaperImage::dispatch($this->record->id)->onQueue('image-processing');
        } catch (\Throwable $e) {
            \Log::warning("Could not queue image processing for wallpaper {$this->record->id}: " . $e->getMessage());
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();
        $data['status'] = auth()->user()->auto_publish ? 'published' : 'pending';

        return $data;
    }
}
