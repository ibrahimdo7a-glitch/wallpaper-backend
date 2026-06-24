<?php
namespace App\Filament\Resources\BrandResource\Pages;
use App\Filament\Resources\BrandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }

    public function getTitle(): string
    {
        return 'ماركة ' . ($this->record->name_ar ?? '');
    }

    public function getBreadcrumb(): string
    {
        return $this->record->name_ar ?? 'تعديل';
    }
}
