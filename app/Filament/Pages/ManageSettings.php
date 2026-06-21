<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Watermark;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class ManageSettings extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $title = 'إعدادات متقدمة';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_manage_settings');
    }

    public function mount(): void
    {
        $keys = [
            'require_review', 'watermark_enabled', 'watermark_force_all',
            'watermark_default_id', 'download_watermarked',
            'likes_enabled', 'downloads_enabled', 'reports_enabled',
            'max_upload_size_mb', 'default_language',
            'meta_title_ar', 'meta_title_en',
            'meta_description_ar', 'meta_description_en',
            'login_attempts_limit', 'login_lockout_minutes',
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
                Forms\Components\Tabs::make('settings_tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('الرفع')
                            ->schema([
                                Forms\Components\TextInput::make('max_upload_size_mb')
                                    ->label('الحد الأقصى للملف (MB)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100),
                                Forms\Components\Select::make('default_language')
                                    ->label('اللغة الافتراضية')
                                    ->options(['ar' => 'العربية', 'en' => 'English']),
                                Forms\Components\Toggle::make('require_review')
                                    ->label('الصور تحتاج مراجعة قبل النشر'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('التوقيع')
                            ->schema([
                                Forms\Components\Toggle::make('watermark_enabled')
                                    ->label('تفعيل التوقيع'),
                                Forms\Components\Toggle::make('watermark_force_all')
                                    ->label('إجبار التوقيع على كل الصور'),
                                Forms\Components\Select::make('watermark_default_id')
                                    ->label('التوقيع الافتراضي')
                                    ->options(fn () => Watermark::active()->pluck('name', 'id')->toArray())
                                    ->nullable(),
                                Forms\Components\Toggle::make('download_watermarked')
                                    ->label('تحميل النسخة الموقعة'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('الميزات')
                            ->schema([
                                Forms\Components\Toggle::make('likes_enabled')->label('الإعجابات'),
                                Forms\Components\Toggle::make('downloads_enabled')->label('التحميل'),
                                Forms\Components\Toggle::make('reports_enabled')->label('البلاغات'),
                            ])->columns(3),

                        Forms\Components\Tabs\Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('meta_title_ar')->label('Meta Title (عربي)'),
                                Forms\Components\TextInput::make('meta_title_en')->label('Meta Title (إنجليزي)'),
                                Forms\Components\Textarea::make('meta_description_ar')->label('Meta Description (عربي)')->rows(2),
                                Forms\Components\Textarea::make('meta_description_en')->label('Meta Description (إنجليزي)')->rows(2),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('الأمان')
                            ->schema([
                                Forms\Components\TextInput::make('login_attempts_limit')
                                    ->label('محاولات الدخول قبل القفل')
                                    ->numeric(),
                                Forms\Components\TextInput::make('login_lockout_minutes')
                                    ->label('مدة القفل (دقيقة)')
                                    ->numeric(),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        $this->revalidateFrontend();

        Notification::make()
            ->title('تم حفظ الإعدادات بنجاح ✓')
            ->success()
            ->send();
    }

    private function revalidateFrontend(): void
    {
        $token = config('app.revalidate_token');
        $url   = config('app.frontend_url') . '/api/revalidate';

        if (! $token || ! $url) {
            return;
        }

        try {
            Http::timeout(5)->withHeaders(['x-revalidate-token' => $token])->post($url);
        } catch (\Throwable) {
            // non-critical — frontend will refresh on its own schedule
        }
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
