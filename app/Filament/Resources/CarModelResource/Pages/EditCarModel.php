<?php
namespace App\Filament\Resources\CarModelResource\Pages;
use App\Filament\Resources\CarModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditCarModel extends EditRecord
{
    protected static string $resource = CarModelResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }

    public function getTitle(): string
    {
        $brand = $this->record->brand?->name_ar;
        return 'موديل ' . ($this->record->name_ar ?? '') . ($brand ? " — {$brand}" : '');
    }

    public function getBreadcrumb(): string
    {
        return $this->record->name_ar ?? 'تعديل';
    }
}
