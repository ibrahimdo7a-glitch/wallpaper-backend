<?php
namespace App\Filament\Resources\ContentItemResource\Pages;
use App\Filament\Resources\ContentItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditContentItem extends EditRecord
{
    protected static string $resource = ContentItemResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
