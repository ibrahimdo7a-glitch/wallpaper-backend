<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PartMarketSettingsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'سوق القطع والاكسسوارات';
    protected static ?int    $navigationSort   = 3;
    protected static string  $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function getTitle(): string|Htmlable { return 'إعدادات سوق القطع والاكسسوارات'; }
    public static function getNavigationLabel(): string { return 'إعدادات السوق'; }

    public static function canAccess(): bool
    {
        return auth()->check() && ! auth()->user()?->hasRole('مبدع');
    }

    public function mount(): void
    {
        $this->form->fill([
            'parts_enabled'  => filter_var(Setting::get('parts_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'parts_label_ar' => Setting::get('parts_label_ar', '') ?: 'قطع وأكسسوارات',
            'parts_label_en' => Setting::get('parts_label_en', '') ?: 'Parts & Accessories',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('parts_market')->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make('عام')->icon('heroicon-o-cog-6-tooth')->schema([
                    Forms\Components\Toggle::make('parts_enabled')->label('تفعيل سوق القطع والاكسسوارات')->inline(false)
                        ->helperText('عند الإطفاء يختفي القسم ورابطه من الموقع.'),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('parts_label_ar')->label('اسم القسم (عربي)')->placeholder('قطع وأكسسوارات'),
                        Forms\Components\TextInput::make('parts_label_en')->label('اسم القسم (إنجليزي)')->placeholder('Parts & Accessories'),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الأقسام والحقول')->icon('heroicon-o-squares-2x2')->schema([
                    Forms\Components\Placeholder::make('sections_help')->label('')
                        ->content('الأقسام (ادبترات، فلاتر، دواليب...) وحقولها تُدار من القائمة الجانبية: «الأقسام والحقول». افتح كل قسم لإضافة خاناته.'),
                ]),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (['parts_enabled', 'parts_label_ar', 'parts_label_en'] as $key) {
            $value = $data[$key] ?? '';
            if (is_bool($value)) { $value = $value ? '1' : '0'; }
            Setting::set($key, $value ?? '');
        }

        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
        $this->revalidateFrontend();

        Notification::make()->title('تم حفظ إعدادات سوق القطع ✓')->success()->send();
    }

    private function revalidateFrontend(): void
    {
        $token = config('app.revalidate_token');
        $url   = config('app.frontend_url') . '/api/revalidate';
        if (! $token || ! $url) { return; }
        try {
            Http::timeout(5)->withHeaders(['x-revalidate-token' => $token])->post($url);
        } catch (\Throwable) {
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')->label('حفظ الإعدادات')->icon('heroicon-o-check')->action('save'),
        ];
    }
}
