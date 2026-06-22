<?php

namespace App\Filament\Resources\HeroBannerResource\Pages;

use App\Filament\Resources\HeroBannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHeroBanner extends CreateRecord
{
    protected static string $resource = HeroBannerResource::class;
    protected function getRedirectUrl(): string { return $this->getResource()::getUrl('index'); }
}
