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
            'welcome_enabled'                  => filter_var(Setting::get('welcome_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'welcome_message'                  => Setting::get('welcome_message', ''),
            'broadcast_enabled'                => filter_var(Setting::get('broadcast_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'broadcast_message'                => Setting::get('broadcast_message', ''),
            'broadcast_audience'               => Setting::get('broadcast_audience', 'all') ?: 'all',
            'broadcast_duration'               => Setting::get('broadcast_duration', 'once') ?: 'once',
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

            Forms\Components\Section::make('رسالة الترحيب بعد التسجيل')
                ->description('تظهر للعضو الجديد مرة واحدة بعد أول تسجيل دخول، في نافذة داخل الموقع.')
                ->schema([
                    Forms\Components\Toggle::make('welcome_enabled')->label('تفعيل رسالة الترحيب')->inline(false),
                    Forms\Components\Textarea::make('welcome_message')->label('نص الترحيب')->rows(4)->maxLength(1000)
                        ->placeholder('أهلًا بك في قناة قطر للسيارات الكهربائية 👋'),
                ]),

            Forms\Components\Section::make('رسالة عامة (إعلان)')
                ->description('تظهر في نافذة داخل الموقع — مرة واحدة لكل زائر. كل حفظ بنص جديد يُعتبر إعلانًا جديدًا يظهر لمن شاهد السابق.')
                ->schema([
                    Forms\Components\Toggle::make('broadcast_enabled')->label('تفعيل الرسالة العامة')->inline(false),
                    Forms\Components\Textarea::make('broadcast_message')->label('نص الرسالة')->rows(4)->maxLength(1000),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('broadcast_audience')->label('تظهر لـ')
                            ->options(['all' => 'جميع الزوّار', 'members' => 'الأعضاء المسجّلون فقط'])->default('all'),
                        Forms\Components\Select::make('broadcast_duration')->label('المدة')
                            ->options(['once' => 'مرة واحدة (حتى تُطفئها)', 'one_day' => 'لمدة يوم واحد'])->default('once')
                            ->helperText('«يوم واحد» تتوقّف تلقائيًا بعد ٢٤ ساعة من الحفظ.'),
                    ]),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        // Snapshot the broadcast before saving, to detect a (re)publish.
        $old = [
            'm' => Setting::get('broadcast_message', ''),
            'a' => Setting::get('broadcast_audience', 'all'),
            'd' => Setting::get('broadcast_duration', 'once'),
            'e' => filter_var(Setting::get('broadcast_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
        ];

        $state = $this->form->getState();
        foreach ($state as $key => $value) {
            if (is_bool($value)) { $value = $value ? '1' : '0'; }
            Setting::set($key, $value ?? '');
        }

        // Re-publishing (new text/audience/duration, or just enabled) bumps the
        // version so everyone who saw the previous one sees the new one once.
        $enabled  = (bool) ($state['broadcast_enabled'] ?? false);
        $message  = (string) ($state['broadcast_message'] ?? '');
        $audience = (string) ($state['broadcast_audience'] ?? 'all');
        $duration = (string) ($state['broadcast_duration'] ?? 'once');
        $changed  = $enabled && $message !== ''
            && ($message !== $old['m'] || $audience !== $old['a'] || $duration !== $old['d'] || ! $old['e']);

        if ($changed) {
            Setting::set('broadcast_id', (string) (((int) Setting::get('broadcast_id', '0')) + 1));
            Setting::set('broadcast_expires_at', $duration === 'one_day' ? now()->addDay()->toIso8601String() : '');
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
