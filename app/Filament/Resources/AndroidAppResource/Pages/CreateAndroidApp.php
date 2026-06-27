<?php

namespace App\Filament\Resources\AndroidAppResource\Pages;

use App\Filament\Resources\AndroidAppResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAndroidApp extends CreateRecord
{
    protected static string $resource = AndroidAppResource::class;

    /**
     * Surface the real error in a notification instead of a generic 500
     * (logs go to stderr on Railway, which isn't reachable from here).
     */
    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (Halt | ValidationException $e) {
            throw $e; // normal Filament flow (validation / halt)
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('خطأ أثناء الحفظ')
                ->body(class_basename($e) . ': ' . Str::limit($e->getMessage(), 500))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    // Keep the legacy brand_id pointing at the first selected brand so the global
    // /apps page, filters and counts keep working.
    protected function afterCreate(): void
    {
        $this->record->update(['brand_id' => $this->record->brands()->first()?->id]);
    }
}
