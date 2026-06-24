<?php
namespace App\Filament\Resources\CarModelResource\Pages;
use App\Filament\Resources\CarModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditCarModel extends EditRecord
{
    protected static string $resource = CarModelResource::class;
    protected function getHeaderActions(): array
    {
        // The "مبدع" creative role can't delete whole models.
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => ! auth()->user()?->hasRole('مبدع')),
        ];
    }

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
