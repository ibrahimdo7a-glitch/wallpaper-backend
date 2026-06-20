<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Watermark;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $title = 'إعدادات الموقع';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.manage-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_manage_settings');
    }

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $this->data = $settings;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('settings_tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('عام')
                            ->schema([
                                Forms\Components\TextInput::make('data.site_name_ar')
                                    ->label('اسم الموقع (عربي)')
                                    ->required(),
                                Forms\Components\TextInput::make('data.site_name_en')
                                    ->label('اسم الموقع (إنجليزي)')
                                    ->required(),
                                Forms\Components\Select::make('data.default_language')
                                    ->label('اللغة الافتراضية')
                                    ->options(['ar' => 'العربية', 'en' => 'English']),
                                Forms\Components\FileUpload::make('data.logo')
                                    ->label('الشعار')
                                    ->image()
                                    ->disk('r2')
                                    ->directory('site'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('الرفع')
                            ->schema([
                                Forms\Components\TextInput::make('data.max_upload_size_mb')
                                    ->label('الحد الأقصى للملف (MB)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100),
                                Forms\Components\Toggle::make('data.require_review')
                                    ->label('الصور تحتاج مراجعة قبل النشر'),
                            ]),

                        Forms\Components\Tabs\Tab::make('التوقيع')
                            ->schema([
                                Forms\Components\Toggle::make('data.watermark_enabled')
                                    ->label('تفعيل التوقيع'),
                                Forms\Components\Toggle::make('data.watermark_force_all')
                                    ->label('إجبار التوقيع على كل الصور'),
                                Forms\Components\Select::make('data.watermark_default_id')
                                    ->label('التوقيع الافتراضي')
                                    ->options(Watermark::active()->pluck('name', 'id'))
                                    ->nullable(),
                                Forms\Components\Toggle::make('data.download_watermarked')
                                    ->label('تحميل النسخة الموقعة'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('الميزات')
                            ->schema([
                                Forms\Components\Toggle::make('data.likes_enabled')->label('الإعجابات'),
                                Forms\Components\Toggle::make('data.downloads_enabled')->label('التحميل'),
                                Forms\Components\Toggle::make('data.reports_enabled')->label('البلاغات'),
                            ])->columns(3),

                        Forms\Components\Tabs\Tab::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('data.meta_title_ar')->label('Meta Title (عربي)'),
                                Forms\Components\TextInput::make('data.meta_title_en')->label('Meta Title (إنجليزي)'),
                                Forms\Components\Textarea::make('data.meta_description_ar')->label('Meta Description (عربي)')->rows(2),
                                Forms\Components\Textarea::make('data.meta_description_en')->label('Meta Description (إنجليزي)')->rows(2),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('الأمان')
                            ->schema([
                                Forms\Components\TextInput::make('data.login_attempts_limit')
                                    ->label('محاولات الدخول قبل القفل')
                                    ->numeric(),
                                Forms\Components\TextInput::make('data.login_lockout_minutes')
                                    ->label('مدة القفل (دقيقة)')
                                    ->numeric(),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->data as $key => $value) {
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        Notification::make()
            ->title('تم حفظ الإعدادات بنجاح')
            ->success()
            ->send();
    }
}
