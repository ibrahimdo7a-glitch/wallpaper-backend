<?php

namespace App\Filament\Pages;

use App\Models\MarketField;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CarMarketSettingsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'سوق السيارات';
    protected static ?int    $navigationSort   = 2;
    protected static string  $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function getTitle(): string|Htmlable { return 'إعدادات سوق السيارات'; }
    public static function getNavigationLabel(): string { return 'إعدادات السوق'; }

    public static function canAccess(): bool
    {
        return auth()->check() && ! auth()->user()?->hasRole('مبدع');
    }

    public function mount(): void
    {
        $data = [
            'cars_enabled'  => filter_var(Setting::get('cars_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'cars_label_ar' => Setting::get('cars_label_ar', '') ?: 'سوق السيارات',
            'cars_label_en' => Setting::get('cars_label_en', '') ?: 'Cars',
        ];

        $data['fields'] = MarketField::where('scope', 'cars')->orderBy('sort_order')->get()
            ->map(fn (MarketField $f) => [
                'id'            => $f->id,
                'label_ar'      => $f->label_ar,
                'is_enabled'    => $f->is_enabled,
                'is_required'   => $f->is_required,
                'is_filterable' => $f->is_filterable,
            ])->all();

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('cars_market')->columnSpanFull()->tabs([
                Forms\Components\Tabs\Tab::make('عام')->icon('heroicon-o-cog-6-tooth')->schema([
                    Forms\Components\Toggle::make('cars_enabled')->label('تفعيل سوق السيارات')->inline(false)
                        ->helperText('عند الإطفاء يختفي القسم ورابطه من الموقع.'),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('cars_label_ar')->label('اسم القسم (عربي)')->placeholder('سوق السيارات'),
                        Forms\Components\TextInput::make('cars_label_en')->label('اسم القسم (إنجليزي)')->placeholder('Cars'),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الحقول')->icon('heroicon-o-list-bullet')->schema([
                    Forms\Components\Placeholder::make('fields_help')->label('')
                        ->content('شغّل أو أطفِ كل حقل يظهر في إعلان السيارة. "مطلوب" = إلزامي عند الإضافة. "فلتر" = يظهر كفلتر للزائر. اسحب لإعادة الترتيب.'),

                    Forms\Components\Repeater::make('fields')
                        ->label('')
                        ->addable(false)->deletable(false)->reorderable()->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => $state['label_ar'] ?? null)
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\Hidden::make('label_ar'),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Toggle::make('is_enabled')->label('مفعّل')->inline(false),
                                Forms\Components\Toggle::make('is_required')->label('مطلوب')->inline(false),
                                Forms\Components\Toggle::make('is_filterable')->label('فلتر')->inline(false),
                            ]),
                        ]),
                ]),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (['cars_enabled', 'cars_label_ar', 'cars_label_en'] as $key) {
            $value = $data[$key] ?? '';
            if (is_bool($value)) { $value = $value ? '1' : '0'; }
            Setting::set($key, $value ?? '');
        }

        foreach (array_values($data['fields'] ?? []) as $i => $row) {
            if (empty($row['id'])) { continue; }
            MarketField::where('id', $row['id'])->update([
                'is_enabled'    => ! empty($row['is_enabled']),
                'is_required'   => ! empty($row['is_required']),
                'is_filterable' => ! empty($row['is_filterable']),
                'sort_order'    => ($i + 1) * 10,
            ]);
        }

        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
        }
        $this->revalidateFrontend();

        Notification::make()->title('تم حفظ إعدادات سوق السيارات ✓')->success()->send();
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
