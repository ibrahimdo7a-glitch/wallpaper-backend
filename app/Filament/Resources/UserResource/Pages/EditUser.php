<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\TelegramService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('linkTelegram')
                ->label('🔗 ربط تلجرام')
                ->icon('heroicon-o-link')
                ->color('info')
                ->modalHeading('ربط تلجرام المشرف')
                ->modalContent(fn () => $this->telegramLinkModal())
                ->action(fn () => null)
                ->modalSubmitActionLabel('إغلاق')
                ->modalCancelAction(false),
            Actions\DeleteAction::make(),
        ];
    }

    private function telegramLinkModal(): HtmlString
    {
        $bot = app(TelegramService::class)->getBotUsername();
        if (! $bot) {
            return new HtmlString('<p class="text-sm text-danger-600" dir="rtl">⚠️ اضبط بوت تلجرام في إعدادات الموقع أولًا.</p>');
        }

        $code = Str::random(24);
        $this->record->update(['telegram_link_code' => $code]);
        $link   = e("https://t.me/{$bot}?start=adm_{$code}");
        $linked = $this->record->telegram_chat_id
            ? '<p class="text-sm text-success-600">✅ مربوط حاليًا — يمكنك إعادة الربط لتحديثه.</p>'
            : '';

        return new HtmlString(<<<HTML
<div class="space-y-4 text-center" dir="rtl">
    {$linked}
    <p class="text-sm text-gray-500 dark:text-gray-400">يفتح المشرف هذا الرابط من تطبيق تلجرام الخاص به، فيُربط حسابه ويبدأ باستقبال إشعارات الإعلانات.</p>
    <a href="{$link}" target="_blank" rel="noopener noreferrer"
       class="inline-flex items-center justify-center gap-2 w-full rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold py-3 px-4 no-underline">
        🔗 افتح تلجرام الآن
    </a>
    <div x-data="{ copied: false }" class="flex items-stretch gap-2">
        <input type="text" readonly value="{$link}"
               class="flex-1 rounded-lg border border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-white/5 text-xs text-gray-600 dark:text-gray-300 px-3 py-2" />
        <button type="button"
                x-on:click="navigator.clipboard.writeText('{$link}'); copied = true; setTimeout(() => copied = false, 1500)"
                class="shrink-0 rounded-lg bg-gray-200 dark:bg-white/10 hover:bg-gray-300 text-gray-800 dark:text-gray-100 text-sm font-semibold px-4">
            <span x-show="!copied">نسخ</span><span x-show="copied" x-cloak>✓ تم</span>
        </button>
    </div>
    <p class="text-xs text-gray-400">أو أرسل الرابط للمشرف ليفتحه من جواله.</p>
</div>
HTML);
    }
}
