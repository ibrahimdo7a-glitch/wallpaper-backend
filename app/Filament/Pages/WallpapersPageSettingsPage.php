<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class WallpapersPageSettingsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'الإعدادات والمظهر';
    protected static ?int    $navigationSort   = 15;
    protected static string  $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function getTitle(): string|Htmlable { return 'صفحة الخلفيات'; }
    public static function getNavigationLabel(): string { return 'صفحة الخلفيات'; }

    public static function canAccess(): bool
    {
        return auth()->check() && ! auth()->user()?->hasRole('مبدع');
    }

    private const KEYS_BOOL = ['wp_enabled', 'wp_show_models', 'wp_show_countries', 'wp_show_sections', 'wp_show_brands', 'wp_featured_enabled'];
    private const DEFAULT_BOOL = ['wp_enabled' => true, 'wp_show_models' => true, 'wp_show_countries' => true, 'wp_show_sections' => true, 'wp_show_brands' => false, 'wp_featured_enabled' => true];

    public function mount(): void
    {
        $fill = [
            'wp_title_ar'     => Setting::get('wp_title_ar', 'معرض الخلفيات'),
            'wp_title_en'     => Setting::get('wp_title_en', 'Wallpapers'),
            'wp_subtitle_ar'  => Setting::get('wp_subtitle_ar', 'أجمل خلفيات السيارات الكهربائية والصينية — مرتّبة حسب الموديل والدولة'),
            'wp_subtitle_en'  => Setting::get('wp_subtitle_en', 'The finest electric & Chinese car wallpapers'),
            'wp_default_sort' => Setting::get('wp_default_sort', 'newest') ?: 'newest',
            'wp_per_page'     => (int) (Setting::get('wp_per_page', 24) ?: 24),
            'wp_featured_count' => (int) (Setting::get('wp_featured_count', 6) ?: 6),
        ];
        foreach (self::KEYS_BOOL as $k) {
            $v = Setting::get($k, null);
            $fill[$k] = $v === null || $v === '' ? self::DEFAULT_BOOL[$k] : filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }
        $this->form->fill($fill);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الصفحة')->schema([
                Forms\Components\Toggle::make('wp_enabled')->label('تفعيل صفحة الخلفيات وإظهارها في القائمة')->inline(false),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('wp_title_ar')->label('العنوان (عربي)')->maxLength(120),
                    Forms\Components\TextInput::make('wp_title_en')->label('العنوان (إنجليزي)')->maxLength(120),
                    Forms\Components\Textarea::make('wp_subtitle_ar')->label('الوصف (عربي)')->rows(2)->maxLength(300),
                    Forms\Components\Textarea::make('wp_subtitle_en')->label('الوصف (إنجليزي)')->rows(2)->maxLength(300),
                ]),
            ]),

            Forms\Components\Section::make('الكلمات الدالة (الفلاتر)')
                ->description('تُبنى تلقائيًا من خلفياتك الفعلية. اختر أي مجموعات تظهر للزائر.')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Toggle::make('wp_show_models')->label('حسب الموديل')->inline(false),
                        Forms\Components\Toggle::make('wp_show_countries')->label('حسب الدولة')->inline(false),
                        Forms\Components\Toggle::make('wp_show_sections')->label('حسب القسم')->inline(false),
                        Forms\Components\Toggle::make('wp_show_brands')->label('حسب الماركة')->inline(false),
                    ]),
                ]),

            Forms\Components\Section::make('العرض')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('wp_default_sort')->label('الترتيب الافتراضي')
                        ->options(['newest' => 'الأحدث', 'views' => 'الأكثر مشاهدة', 'downloads' => 'الأكثر تحميلًا'])->default('newest'),
                    Forms\Components\TextInput::make('wp_per_page')->label('عدد لكل صفحة')->numeric()->minValue(6)->maxValue(60)->default(24),
                    Forms\Components\TextInput::make('wp_featured_count')->label('عدد الخلفيات المميّزة بالأعلى')->numeric()->minValue(0)->maxValue(12)->default(6),
                ]),
                Forms\Components\Toggle::make('wp_featured_enabled')->label('إظهار شريط «الخلفيات المميّزة» بالأعلى')->inline(false),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (is_bool($value)) { $value = $value ? '1' : '0'; }
            Setting::set($key, $value ?? '');
        }
        $this->revalidateFrontend();
        Notification::make()->title('تم حفظ إعدادات صفحة الخلفيات ✓')->success()->send();
    }

    private function revalidateFrontend(): void
    {
        $token = config('app.revalidate_token');
        $url   = config('app.frontend_url') . '/api/revalidate';
        if (! $token || ! $url) return;
        try {
            \Illuminate\Support\Facades\Http::timeout(5)->withHeaders(['x-revalidate-token' => $token])->post($url);
        } catch (\Throwable) {
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')->label('حفظ')->icon('heroicon-o-check')->action('save'),
        ];
    }
}
