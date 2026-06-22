<?php
namespace App\Filament\Resources\SectionTypeResource\Pages;
use App\Filament\Resources\SectionTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditSectionType extends EditRecord
{
    protected static string $resource = SectionTypeResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
