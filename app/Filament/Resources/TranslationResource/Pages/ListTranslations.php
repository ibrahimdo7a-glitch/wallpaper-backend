<?php
namespace App\Filament\Resources\TranslationResource\Pages;
use App\Filament\Resources\TranslationResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
class ListTranslations extends ListRecords
{
    protected static string $resource = TranslationResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()->label('إضافة ترجمة')]; }
    
}
