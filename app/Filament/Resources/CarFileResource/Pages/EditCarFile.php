<?php
namespace App\Filament\Resources\CarFileResource\Pages;
use App\Filament\Resources\CarFileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditCarFile extends EditRecord
{
    protected static string $resource = CarFileResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
