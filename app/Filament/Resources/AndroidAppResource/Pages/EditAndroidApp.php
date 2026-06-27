<?php

namespace App\Filament\Resources\AndroidAppResource\Pages;

use App\Filament\Resources\AndroidAppResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAndroidApp extends EditRecord
{
    protected static string $resource = AndroidAppResource::class;

    public function getTitle(): string
    {
        return 'تطبيق ' . ($this->getRecord()->title_ar ?: '');
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $this->record->update(['brand_id' => $this->record->brands()->first()?->id]);
    }
}
