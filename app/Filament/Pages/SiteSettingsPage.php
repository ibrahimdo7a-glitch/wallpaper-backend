<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

class SiteSettingsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return 'إعدادات الموقع';
    }

    public static function getNavigationLabel(): string
    {
        return 'إعدادات الموقع';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $keys = [
            'site_name_ar', 'site_name_en',
            'hero_title_ar', 'hero_title_en',
            'hero_subtitle_ar', 'hero_subtitle_en',
            'search_placeholder_ar', 'search_placeholder_en',
            'popular_tags_ar', 'popular_tags_en',
            'feature_car_ar', 'feature_car_en',
            'feature_quality_ar', 'feature_quality_en',
            'feature_fast_ar', 'feature_fast_en',
            'footer_copyright_ar', 'footer_copyright_en',
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = Setting::get($key, '');
        }

        $this->form->fill($formData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('إعدادات الموقع')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('الموقع')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('site_name_ar')
                                        ->label('اسم الموقع (عربي)')
                                        ->required()
                                        ->placeholder('خلفيات ليوبارد'),
                                    Forms\Components\TextInput::make('site_name_en')
                                        ->label('اسم الموقع (إنجليزي)')
                                        ->required()
                                        ->placeholder('Leopard Wallpapers'),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('الصفحة الرئيسية')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('hero_title_ar')
                                        ->label('العنوان الرئيسي (عربي)')
                                        ->placeholder('خلفيات ليوبارد'),
                                    Forms\Components\TextInput::make('hero_title_en')
                                        ->label('العنوان الرئيسي (إنجليزي)')
                                        ->placeholder('Leopard Wallpapers'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('hero_subtitle_ar')
                                        ->label('العنوان الفرعي (عربي)')
                                        ->placeholder('خلفيات عالية الجودة لعائلة ليوبارد'),
                                    Forms\Components\TextInput::make('hero_subtitle_en')
                                        ->label('العنوان الفرعي (إنجليزي)')
                                        ->placeholder('Premium wallpapers for the Leopard family'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('search_placeholder_ar')
                                        ->label('نص خانة البحث (عربي)')
                                        ->placeholder('ابحث عن خلفيات ليوبارد...'),
                                    Forms\Components\TextInput::make('search_placeholder_en')
                                        ->label('نص خانة البحث (إنجليزي)')
                                        ->placeholder('Search for Leopard wallpapers...'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('popular_tags_ar')
                                        ->label('الوسوم الشائعة (عربي)')
                                        ->helperText('مفصولة بفاصلة، مثال: 4K, ليلي, صحراوي, ليوبارد 5')
                                        ->placeholder('4K, ليلي, صحراوي, ليوبارد 5, ليوبارد 8'),
                                    Forms\Components\TextInput::make('popular_tags_en')
                                        ->label('الوسوم الشائعة (إنجليزي)')
                                        ->helperText('Comma-separated, e.g.: 4K, Night, Desert, Leopard 5')
                                        ->placeholder('4K, Night, Desert, Leopard 5, Leopard 8'),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('الميزات')
                            ->icon('heroicon-o-star')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('feature_car_ar')
                                        ->label('ميزة شاشة السيارة (عربي)')
                                        ->placeholder('مناسبة لشاشة السيارة'),
                                    Forms\Components\TextInput::make('feature_car_en')
                                        ->label('ميزة شاشة السيارة (إنجليزي)')
                                        ->placeholder('Car Screen Ready'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('feature_quality_ar')
                                        ->label('ميزة الجودة (عربي)')
                                        ->placeholder('جودة عالية'),
                                    Forms\Components\TextInput::make('feature_quality_en')
                                        ->label('ميزة الجودة (إنجليزي)')
                                        ->placeholder('High Quality'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('feature_fast_ar')
                                        ->label('ميزة السرعة (عربي)')
                                        ->placeholder('خفيفة وسريعة'),
                                    Forms\Components\TextInput::make('feature_fast_en')
                                        ->label('ميزة السرعة (إنجليزي)')
                                        ->placeholder('Fast & Light'),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('التذييل')
                            ->icon('heroicon-o-bars-3-bottom-left')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('footer_copyright_ar')
                                        ->label('حقوق النشر (عربي)')
                                        ->placeholder('© 2025 خلفيات ليوبارد. جميع الحقوق محفوظة.'),
                                    Forms\Components\TextInput::make('footer_copyright_en')
                                        ->label('حقوق النشر (إنجليزي)')
                                        ->placeholder('© 2025 Leopard Wallpapers. All rights reserved.'),
                                ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        // Setting::set() already invalidates per-key cache; no global flush needed
        \Illuminate\Support\Facades\Cache::forget('categories.tree');

        Notification::make()
            ->title('تم حفظ الإعدادات بنجاح ✓')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('حفظ الإعدادات')
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }
}
