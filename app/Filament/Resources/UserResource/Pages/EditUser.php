<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\TelegramService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('linkTelegram')
                ->label('🔗 رابط ربط تلجرام')
                ->icon('heroicon-o-link')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('إنشاء رابط ربط تلجرام')
                ->modalDescription('سيُنشأ رابط لمرة واحدة. أرسله للمشرف ليفتحه من تطبيق تلجرام الخاص به، فيُربط حسابه وتصله الإشعارات.')
                ->modalSubmitActionLabel('إنشاء الرابط')
                ->action(function () {
                    $bot = app(TelegramService::class)->getBotUsername();
                    if (! $bot) {
                        Notification::make()->title('اضبط بوت تلجرام في إعدادات الموقع أولًا')->danger()->send();
                        return;
                    }
                    $code = Str::random(24);
                    $this->record->update(['telegram_link_code' => $code]);
                    $link = "https://t.me/{$bot}?start=adm_{$code}";
                    Notification::make()
                        ->title('انسخ الرابط وأرسله للمشرف ليفتحه من تلجرامه')
                        ->body($link)
                        ->info()->persistent()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
