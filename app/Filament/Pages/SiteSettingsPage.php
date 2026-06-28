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

class SiteSettingsPage extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'الإعدادات والمظهر';

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
        // Holds the Telegram bot token + AI key — super admin only.
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $keys = [
            'site_name_ar', 'site_name_en',
            'hero_title_ar', 'hero_title_en',
            'hero_subtitle_ar', 'hero_subtitle_en',
            'search_enabled',
            'search_placeholder_ar', 'search_placeholder_en',
            'popular_tags_ar', 'popular_tags_en',
            'feature_car_ar', 'feature_car_en',
            'feature_quality_ar', 'feature_quality_en',
            'feature_fast_ar', 'feature_fast_en',
            'footer_copyright_ar', 'footer_copyright_en',
            'telegram_bot_token', 'telegram_channel_id',
            'telegram_topic_id', 'telegram_topic_id_apps', 'telegram_topic_id_news',
            'stat_visitors_enabled', 'stat_downloads_enabled', 'stat_wallpapers_enabled', 'stat_apps_enabled',
            'stat_likes_enabled', 'stat_views_enabled', 'stat_news_enabled',
            'stat_visitors_value', 'stat_downloads_value', 'stat_wallpapers_value', 'stat_apps_value',
            'stat_likes_value', 'stat_views_value', 'stat_news_value',
            'stat_visitors_order', 'stat_downloads_order', 'stat_wallpapers_order', 'stat_apps_order',
            'stat_likes_order', 'stat_views_order', 'stat_news_order',
            'ai_enabled', 'ai_api_key', 'ai_model', 'ai_translation_prompt', 'ai_summarize_prompt',
        ];

        $formData = [];
        foreach ($keys as $key) {
            $formData[$key] = Setting::get($key, '');
        }

        // search is enabled by default when no setting exists yet
        $formData['search_enabled'] = $formData['search_enabled'] === ''
            ? true
            : filter_var($formData['search_enabled'], FILTER_VALIDATE_BOOLEAN);

        // statistics toggles default to ON until explicitly turned off
        foreach (['stat_visitors_enabled', 'stat_downloads_enabled', 'stat_wallpapers_enabled', 'stat_apps_enabled', 'stat_likes_enabled', 'stat_views_enabled', 'stat_news_enabled'] as $sk) {
            $formData[$sk] = $formData[$sk] === ''
                ? true
                : filter_var($formData[$sk], FILTER_VALIDATE_BOOLEAN);
        }

        // AI: default enabled; show the default prompts so the admin can read/edit them
        $ai = app(\App\Services\AiService::class);
        $formData['ai_enabled'] = $formData['ai_enabled'] === ''
            ? true
            : filter_var($formData['ai_enabled'], FILTER_VALIDATE_BOOLEAN);
        $formData['ai_model'] = $formData['ai_model'] ?: 'claude-haiku-4-5-20251001';
        $formData['ai_translation_prompt'] = $formData['ai_translation_prompt'] ?: $ai->defaultTranslationPrompt();
        $formData['ai_summarize_prompt']   = $formData['ai_summarize_prompt'] ?: $ai->defaultSummarizePrompt();

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
                                Forms\Components\Toggle::make('search_enabled')
                                    ->label('تفعيل محرك البحث في الصفحة الرئيسية')
                                    ->helperText('عند الإطفاء يختفي شريط البحث والوسوم الشائعة من الـ Hero')
                                    ->default(true),
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

                        Forms\Components\Tabs\Tab::make('الإحصائيات')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Forms\Components\Placeholder::make('stats_help')
                                    ->label('')
                                    ->content('فعّل أو ألغِ كل إحصائية (٦ كحد أقصى، ٣×٢ بجانب الماركات). خانة "الترتيب": اكتب ١ للي تبيه أول، ٢ للي بعده… (فاضية = الترتيب الافتراضي). خانة "رقم يدوي": فاضية = العدد الحقيقي تلقائيًا.'),

                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_visitors_enabled')->label('👁️ عدد الزوار')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_visitors_order')->label('الترتيب')->numeric()->placeholder('مثل: 1'),
                                    Forms\Components\TextInput::make('stat_visitors_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_downloads_enabled')->label('⬇️ إجمالي التحميلات')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_downloads_order')->label('الترتيب')->numeric()->placeholder('مثل: 2'),
                                    Forms\Components\TextInput::make('stat_downloads_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_wallpapers_enabled')->label('🖼️ عدد الخلفيات')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_wallpapers_order')->label('الترتيب')->numeric()->placeholder('مثل: 1'),
                                    Forms\Components\TextInput::make('stat_wallpapers_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_apps_enabled')->label('📱 عدد التطبيقات')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_apps_order')->label('الترتيب')->numeric()->placeholder('مثل: 3'),
                                    Forms\Components\TextInput::make('stat_apps_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_likes_enabled')->label('❤️ عدد الإعجابات')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_likes_order')->label('الترتيب')->numeric()->placeholder('مثل: 6'),
                                    Forms\Components\TextInput::make('stat_likes_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_views_enabled')->label('👀 عدد المشاهدات')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_views_order')->label('الترتيب')->numeric()->placeholder('مثل: 4'),
                                    Forms\Components\TextInput::make('stat_views_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\Toggle::make('stat_news_enabled')->label('📰 عدد الأخبار')->inline(false)->default(true),
                                    Forms\Components\TextInput::make('stat_news_order')->label('الترتيب')->numeric()->placeholder('مثل: 7'),
                                    Forms\Components\TextInput::make('stat_news_value')->label('رقم يدوي (اختياري)')->numeric()->placeholder('تلقائي'),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('الذكاء الاصطناعي')
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Forms\Components\Placeholder::make('ai_help')
                                    ->label('')
                                    ->content('يُشغّل زر "الترجمة التلقائية" وزر "توليد المقال من رابط" في الأخبار/التطبيقات. أنشئ مفتاحًا من console.anthropic.com وأضف رصيدًا بسيطًا (~٥$ تكفي شهورًا).'),

                                Forms\Components\Toggle::make('ai_enabled')->label('تفعيل الذكاء الاصطناعي')->inline(false)->default(true),

                                Forms\Components\TextInput::make('ai_api_key')->label('مفتاح Anthropic API')
                                    ->password()->revealable()
                                    ->placeholder('sk-ant-...')
                                    ->helperText('يُحفظ بأمان ولا يظهر للزوّار إطلاقًا.')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('ai_model')->label('الموديل')
                                    ->options([
                                        'claude-haiku-4-5-20251001' => 'Claude Haiku — سريع ورخيص (موصى به)',
                                        'claude-sonnet-4-6'         => 'Claude Sonnet — أذكى وأغلى',
                                    ])
                                    ->default('claude-haiku-4-5-20251001'),

                                Forms\Components\Textarea::make('ai_translation_prompt')->label('✍️ تعليمات الترجمة')
                                    ->rows(5)->columnSpanFull()
                                    ->helperText('وجّه الذكاء كيف يترجم (الأسلوب، الاختصار…). عدّلها متى ما تبي.'),

                                Forms\Components\Textarea::make('ai_summarize_prompt')->label('✍️ تعليمات تلخيص المقال من رابط')
                                    ->rows(5)->columnSpanFull()
                                    ->helperText('وجّه الذكاء كيف يستخرج المهم ويلخّص الخبر من الرابط.'),
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

                        Forms\Components\Tabs\Tab::make('تلجرام')
                            ->icon('heroicon-o-paper-airplane')
                            ->schema([
                                Forms\Components\TextInput::make('telegram_bot_token')
                                    ->label('توكن البوت (Bot Token)')
                                    ->helperText('أنشئ بوت من @BotFather واحصل على التوكن. مثال: 123456789:ABCdef...')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('telegram_channel_id')
                                    ->label('معرّف القناة')
                                    ->helperText('مثال: ‎@Qatar_ev‎ أو ‎-1001234567890‎. لازم تضيف البوت كمشرف في القناة أولاً.')
                                    ->columnSpanFull(),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('telegram_topic_id')
                                        ->label('رقم قسم الخلفيات (Topic)')
                                        ->numeric()
                                        ->helperText('قسم "خلفيات الشاشة".'),
                                    Forms\Components\TextInput::make('telegram_topic_id_apps')
                                        ->label('رقم قسم البرامج (Topic)')
                                        ->numeric()
                                        ->helperText('قسم البرامج/التطبيقات.'),
                                    Forms\Components\TextInput::make('telegram_topic_id_news')
                                        ->label('رقم قسم الأخبار (Topic)')
                                        ->numeric()
                                        ->helperText('قسم الأخبار.'),
                                ]),
                                Forms\Components\Placeholder::make('telegram_topic_help')
                                    ->label('')
                                    ->content('تحصل رقم كل قسم من رابط أي رسالة فيه: t.me/Qatar_ev/الرقم/... — اتركه فارغًا = النشر في الواجهة الرئيسية.'),
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
            // store toggles as explicit '1'/'0' so "off" isn't confused with "never set"
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            Setting::set($key, $value ?? '');
        }

        // Refresh cached homepage payloads so statistics/settings changes show immediately.
        foreach (['ar', 'en'] as $locale) {
            Cache::forget("homepage.data.{$locale}");
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

    // Header actions (Filament executes their closures reliably here, unlike form actions).
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('set_webhook')
                ->label('ربط البوت (دخول الأعضاء + أزرار المراجعة)')
                ->icon('heroicon-o-bolt')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('يربط بوت تلجرام لاستقبال دخول الأعضاء وأزرار نشر/رفض الإعلانات داخل تلجرام. اضغط بعد ضبط توكن البوت في تبويب «تلجرام». (مطلوب مرة واحدة بعد كل تحديث لأزرار المراجعة.)')
                ->action(function () {
                    $tg = app(\App\Services\TelegramService::class);
                    if (! $tg->hasBot()) {
                        Notification::make()->title('أضف توكن البوت أولًا')->warning()->persistent()->send();
                        return;
                    }
                    $url = \App\Http\Controllers\Api\V1\TelegramAuthController::webhookUrl(request()->getSchemeAndHttpHost());
                    $res = $tg->setWebhook($url);
                    if ($res['ok']) {
                        Notification::make()->title('تم ربط البوت ✓')->body($tg->getWebhookInfo()['url'] ?? $url)->success()->persistent()->send();
                    } else {
                        Notification::make()->title('فشل الربط')->body(($res['error'] ?? 'خطأ') . ' — ' . $url)->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
