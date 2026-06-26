<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class MembersSettingsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'الأعضاء';
    protected static ?int    $navigationSort   = 2;
    protected static string  $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function getTitle(): string|Htmlable { return 'إعدادات الأعضاء'; }
    public static function getNavigationLabel(): string { return 'إعدادات الأعضاء'; }

    public static function canAccess(): bool
    {
        return auth()->check() && ! auth()->user()?->hasRole('مبدع');
    }

    public function mount(): void
    {
        $this->form->fill([
            'member_listings_enabled'          => filter_var(Setting::get('member_listings_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
            'member_listings_require_approval' => filter_var(Setting::get('member_listings_require_approval', '1'), FILTER_VALIDATE_BOOLEAN),
            'telegram_admin_chat_id'           => Setting::get('telegram_admin_chat_id', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('نشر الأعضاء للإعلانات')->schema([
                Forms\Components\Toggle::make('member_listings_enabled')->label('السماح للأعضاء بنشر إعلانات')->inline(false)
                    ->helperText('عند الإطفاء يختفي زر «أضف إعلان» للأعضاء.'),
                Forms\Components\Toggle::make('member_listings_require_approval')->label('مراجعة الإعلان قبل النشر')->inline(false)
                    ->helperText('مُستحسن: الإعلان يبقى «بانتظار المراجعة» حتى توافق عليه.'),
            ]),

            Forms\Components\Section::make('إشعارات تلجرام للإدارة')->schema([
                Forms\Components\TextInput::make('telegram_admin_chat_id')->label('رقمك التعريفي في تلجرام (Chat ID)')
                    ->helperText('يصلك إشعار بالبوت عند كل إعلان جديد من عضو. أرسل /start للبوت @userinfobot ليعطيك رقمك، أو اتركه فارغًا لإيقاف الإشعار.')
                    ->numeric(),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (is_bool($value)) { $value = $value ? '1' : '0'; }
            Setting::set($key, $value ?? '');
        }
        Notification::make()->title('تم حفظ إعدادات الأعضاء ✓')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')->label('حفظ الإعدادات')->icon('heroicon-o-check')->action('save'),
        ];
    }
}
