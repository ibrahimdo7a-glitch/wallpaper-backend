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
            'terms_ar', 'terms_en',
            'privacy_ar', 'privacy_en',
            'about_ar', 'about_en',
            'contact_enabled',
            'telegram_bot_token', 'telegram_channel_id',
            'telegram_topic_id', 'telegram_topic_id_apps', 'telegram_topic_id_news', 'telegram_topic_id_market', 'telegram_topic_id_parts',
            'stat_visitors_enabled', 'stat_downloads_enabled', 'stat_wallpapers_enabled', 'stat_apps_enabled',
            'stat_likes_enabled', 'stat_views_enabled', 'stat_news_enabled',
            'stat_visitors_value', 'stat_downloads_value', 'stat_wallpapers_value', 'stat_apps_value',
            'stat_likes_value', 'stat_views_value', 'stat_news_value',
            'stat_visitors_order', 'stat_downloads_order', 'stat_wallpapers_order', 'stat_apps_order',
            'stat_likes_order', 'stat_views_order', 'stat_news_order',
            'ai_enabled', 'ai_api_key', 'ai_model', 'ai_translation_prompt', 'ai_summarize_prompt',
            'site_favicon_path', 'og_image_path',
            'seo_google_verification', 'seo_bing_verification',
            'seo_keywords_ar', 'seo_keywords_en',
            'admin_2fa_enabled',
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

        // Admin 2FA: OFF until explicitly enabled (prevents lockout on first deploy).
        $formData['admin_2fa_enabled'] = filter_var($formData['admin_2fa_enabled'], FILTER_VALIDATE_BOOLEAN);

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
                                Forms\Components\FileUpload::make('site_favicon_path')
                                    ->label('أيقونة الموقع (Favicon)')
                                    ->helperText('الأيقونة التي تظهر في تبويب المتصفح وعلى الجوال — تُطبَّق تلقائيًا على كل المتصفحات. يُفضَّل صورة مربّعة بخلفية شفافة (PNG). استخدم أداة القص لجعلها مربّعة، وغيّرها وقت ما تشاء (مثلاً في المناسبات).')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['1:1'])
                                    ->disk(config('filesystems.default', 'public'))
                                    ->directory('site')
                                    ->visibility('private')
                                    ->maxSize(2048),
                                Forms\Components\FileUpload::make('og_image_path')
                                    ->label('صورة المشاركة (عند نشر الروابط)')
                                    ->helperText('الصورة التي تظهر عند مشاركة أي رابط من الموقع في تلجرام/واتساب/تويتر. يُفضَّل مقاس 1200×630 (أفقي). اتركها فارغة لاستخدام صورة QEV الافتراضية. ملاحظة: تلجرام يخزّن المعاينة — حدّثها عبر @WebpageBot بعد التغيير.')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['1.91:1'])
                                    ->disk(config('filesystems.default', 'public'))
                                    ->directory('site')
                                    ->visibility('private')
                                    ->maxSize(4096),
                                Forms\Components\Toggle::make('admin_2fa_enabled')
                                    ->label('تحقّق ثنائي للوحة التحكم عبر تيليجرام (2FA)')
                                    ->helperText('عند التفعيل: بعد الإيميل وكلمة المرور، يُرسَل رمز من ٤ أرقام إلى تيليجرام المشرف قبل الدخول. يعمل فقط للمشرفين الذين ربطوا حسابهم بالبوت (زر «ربط البوت»). للطوارئ يمكن تعطيله بضبط ADMIN_2FA_DISABLED=true في متغيّرات Railway.'),
                                Forms\Components\TextInput::make('admin_2fa_recovery_code')
                                    ->label('رمز الدخول الاحتياطي (للطوارئ — سوبر أدمن فقط)')
                                    ->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn () => (\App\Models\Setting::get('admin_2fa_recovery_hash', '') ? '✅ يوجد رمز محفوظ حاليًا. ' : '⚠️ لا يوجد رمز بعد. ')
                                        . 'رمز طويل (٨ أحرف فأكثر) تُدخله بدل رمز تيليجرام لو تعطّل البوت. يُخزَّن مشفّرًا ولا يُعرض أبدًا. اتركه فارغًا للإبقاء على الحالي. احفظه في مدير كلمات مرور.'),
                            ]),

                        Forms\Components\Tabs\Tab::make('SEO ومحركات البحث')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Forms\Components\Placeholder::make('seo_help')
                                    ->label('')
                                    ->content('بعد إنشاء حساب في Google Search Console و Bing Webmaster، الصق رمز التحقق هنا واحفظ — يظهر تلقائيًا في كل صفحات الموقع. ثم أرسل خريطة الموقع: https://qev.app/sitemap.xml'),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('seo_google_verification')
                                        ->label('رمز تحقق Google')
                                        ->helperText('من Google Search Console → إضافة موقع → علامة HTML. الصق قيمة content فقط.')
                                        ->placeholder('مثال: aBcD1234...'),
                                    Forms\Components\TextInput::make('seo_bing_verification')
                                        ->label('رمز تحقق Bing')
                                        ->helperText('من Bing Webmaster Tools → علامة HTML (msvalidate.01).')
                                        ->placeholder('مثال: 1A2B3C...'),
                                ]),
                                Forms\Components\Textarea::make('seo_keywords_ar')
                                    ->label('كلمات مفتاحية (عربي) — مفصولة بفواصل')
                                    ->rows(2)
                                    ->helperText('اتركها فارغة لاستخدام الكلمات الافتراضية القوية المضمّنة في الموقع.'),
                                Forms\Components\Textarea::make('seo_keywords_en')
                                    ->label('كلمات مفتاحية (إنجليزي) — مفصولة بفواصل')
                                    ->rows(2),
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

                        Forms\Components\Tabs\Tab::make('الشروط والأحكام')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Placeholder::make('terms_help')->label('')
                                    ->content('اكتب الشروط والأحكام هنا — تظهر مباشرة في صفحة /terms بالموقع. اتركها فارغة لعرض النص الافتراضي.'),
                                Forms\Components\RichEditor::make('terms_ar')->label('الشروط (عربي)')
                                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('terms_en')->label('الشروط (إنجليزي)')
                                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('سياسة الخصوصية')
                            ->icon('heroicon-o-lock-closed')
                            ->schema([
                                Forms\Components\Placeholder::make('privacy_help')->label('')
                                    ->content('تظهر مباشرة في صفحة /privacy. اتركها فارغة لعرض النص الافتراضي.'),
                                Forms\Components\RichEditor::make('privacy_ar')->label('سياسة الخصوصية (عربي)')
                                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('privacy_en')->label('سياسة الخصوصية (إنجليزي)')
                                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('من نحن')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Placeholder::make('about_help')->label('')
                                    ->content('تظهر مباشرة في صفحة /about. اتركها فارغة لإخفاء الصفحة من التذييل.'),
                                Forms\Components\RichEditor::make('about_ar')->label('من نحن (عربي)')
                                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('about_en')->label('من نحن (إنجليزي)')
                                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('تواصل معنا')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Forms\Components\Toggle::make('contact_enabled')->label('تفعيل زر «تواصل معنا» في الموقع')->default(true)
                                    ->helperText('يظهر زر في تذييل الموقع، يكتب العضو رسالة قصيرة (حتى ٢٠٠ حرف) تصلك على تلجرام.'),
                                Forms\Components\Placeholder::make('contact_messages')
                                    ->label('آخر الرسائل (حتى ٥٠ — تُحذف الأقدم تلقائيًا)')
                                    ->content(function () {
                                        $msgs = \App\Models\ContactMessage::orderByDesc('id')->limit(50)->get();
                                        if ($msgs->isEmpty()) {
                                            return 'لا توجد رسائل بعد.';
                                        }
                                        $html = '<div style="max-height:420px;overflow:auto;display:flex;flex-direction:column;gap:8px">';
                                        foreach ($msgs as $m) {
                                            $html .= '<div style="border:1px solid rgba(120,120,120,.25);border-radius:10px;padding:10px">'
                                                   . '<div style="font-weight:600;font-size:12px;opacity:.7">' . e($m->member_name) . ' · ' . $m->created_at?->diffForHumans() . '</div>'
                                                   . '<div style="margin-top:4px">' . e($m->message) . '</div></div>';
                                        }
                                        return new \Illuminate\Support\HtmlString($html . '</div>');
                                    }),
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
                                Forms\Components\Grid::make(2)->schema([
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
                                    Forms\Components\TextInput::make('telegram_topic_id_market')
                                        ->label('رقم قسم إعلانات السيارات (Topic)')
                                        ->numeric()
                                        ->helperText('إعلانات سوق السيارات (بيع/طلب سيارة) تُنشر في هذا القسم.'),
                                    Forms\Components\TextInput::make('telegram_topic_id_parts')
                                        ->label('رقم قسم إعلانات قطع الغيار (Topic)')
                                        ->numeric()
                                        ->helperText('إعلانات القطع والإكسسوارات تُنشر في هذا القسم. لو تركته فارغًا تروح لقسم السيارات.'),
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

        // Backup recovery code (2FA fallback): hash it — never persist plaintext — and
        // keep it out of the generic settings loop below.
        if (filled($data['admin_2fa_recovery_code'] ?? null)) {
            $recovery = trim((string) $data['admin_2fa_recovery_code']);
            if (strlen($recovery) < 8) {
                Notification::make()->title('الرمز الاحتياطي قصير — استخدم ٨ أحرف على الأقل')->danger()->send();
                return;
            }
            Setting::set('admin_2fa_recovery_hash', \Illuminate\Support\Facades\Hash::make($recovery));
        }
        unset($data['admin_2fa_recovery_code']);

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
