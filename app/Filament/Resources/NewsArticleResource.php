<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsArticleResource\Pages;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class NewsArticleResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = NewsArticle::class;
    protected static ?string $navigationIcon  = 'heroicon-o-newspaper';
    protected static ?string $navigationGroup = 'الأخبار';
    protected static ?int    $navigationSort  = 10;

    public static function getNavigationLabel(): string  { return 'المقالات'; }
    public static function getModelLabel(): string       { return 'مقال'; }
    public static function getPluralModelLabel(): string { return 'المقالات'; }

    public static function form(Form $form): Form
    {
        $disk = config('filesystems.default', 'public');

        return $form->schema([
            Forms\Components\Section::make('🤖 مساعد الذكاء الاصطناعي')
                ->description('ولّد المقال من رابط خبر، أو ترجم العربي للإنجليزي بضغطة.')
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('import_url')->label('رابط خبر مصدر (أي لغة)')
                        ->url()->dehydrated(false)->columnSpanFull()
                        ->placeholder('https://...')
                        ->helperText('الصق رابطًا واضغط "توليد المقال" — يستخرج المهم، يلخّص، يترجم، ويملأ الحقول. (الصور تضيفها يدويًا)'),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('ai_generate')
                            ->label('✨ توليد المقال من الرابط')
                            ->icon('heroicon-o-sparkles')->color('primary')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $ai = app(\App\Services\AiService::class);
                                if (! $ai->isConfigured()) {
                                    \Filament\Notifications\Notification::make()->title('فعّل الذكاء وأضف المفتاح في إعدادات الموقع أولًا')->danger()->send();
                                    return;
                                }
                                $url = trim((string) $get('import_url'));
                                if (! $url) {
                                    \Filament\Notifications\Notification::make()->title('الصق رابطًا أولًا')->warning()->send();
                                    return;
                                }
                                $r = $ai->articleFromUrl($url);
                                if (! $r) {
                                    \Filament\Notifications\Notification::make()->title('تعذّر توليد المقال')->body($ai->lastError ?? '')->danger()->send();
                                    return;
                                }
                                foreach (['title_ar', 'summary_ar', 'content_ar', 'title_en', 'summary_en', 'content_en'] as $f) {
                                    if (filled($r[$f] ?? null)) $set($f, $r[$f]);
                                }
                                $set('source_url', $url);
                                \Filament\Notifications\Notification::make()->title('تم توليد المقال — راجعه ثم انشر ✓')->success()->send();
                            }),
                        Forms\Components\Actions\Action::make('ai_translate')
                            ->label('🔁 ترجم العربي → إنجليزي')
                            ->icon('heroicon-o-language')->color('gray')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $ai = app(\App\Services\AiService::class);
                                if (! $ai->isConfigured()) {
                                    \Filament\Notifications\Notification::make()->title('فعّل الذكاء وأضف المفتاح في الإعدادات')->danger()->send();
                                    return;
                                }
                                $r = $ai->translate([
                                    'title'            => $get('title_ar'),
                                    'summary'          => $get('summary_ar'),
                                    'content'          => $get('content_ar'),
                                    'meta_title'       => $get('meta_title_ar'),
                                    'meta_description' => $get('meta_description_ar'),
                                ]);
                                if (! $r) {
                                    \Filament\Notifications\Notification::make()->title('تعذّرت الترجمة')->body($ai->lastError ?? '')->danger()->send();
                                    return;
                                }
                                foreach (['title' => 'title_en', 'summary' => 'summary_en', 'content' => 'content_en', 'meta_title' => 'meta_title_en', 'meta_description' => 'meta_description_en'] as $src => $dst) {
                                    if (filled($r[$src] ?? null)) $set($dst, $r[$src]);
                                }
                                \Filament\Notifications\Notification::make()->title('تمت الترجمة ✓')->success()->send();
                            }),
                    ]),
                ]),

            Forms\Components\Tabs::make()->tabs([

                Forms\Components\Tabs\Tab::make('المحتوى')->icon('heroicon-o-document-text')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(300)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                $context === 'create'
                                    ? $set('slug', Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state)) . '-' . Str::random(4))
                                    : null
                            ),
                        Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(300),
                    ]),

                    Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('summary_ar')->label('ملخص (عربي)')->rows(3),
                        Forms\Components\Textarea::make('summary_en')->label('ملخص (إنجليزي)')->rows(3),
                    ]),

                    Forms\Components\RichEditor::make('content_ar')->label('المحتوى الكامل (عربي)')
                        ->toolbarButtons(['bold', 'italic', 'strike', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'attachFiles', 'undo', 'redo'])
                        ->fileAttachmentsDisk($disk)->fileAttachmentsDirectory('news/content')->fileAttachmentsVisibility('private')
                        ->helperText('استخدم شريط الأدوات: عناوين، روابط، قوائم، اقتباس، و📷 لإضافة صور بين الفقرات. الإيموجي اكتبها مباشرة.')
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('content_en')->label('المحتوى الكامل (إنجليزي)')
                        ->toolbarButtons(['bold', 'italic', 'strike', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'attachFiles', 'undo', 'redo'])
                        ->fileAttachmentsDisk($disk)->fileAttachmentsDirectory('news/content')->fileAttachmentsVisibility('private')
                        ->columnSpanFull(),
                ]),

                Forms\Components\Tabs\Tab::make('التصنيف والربط')->icon('heroicon-o-tag')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('news_category_id')->label('التصنيف')
                            ->options(NewsCategory::active()->orderBy('sort_order')->pluck('name_ar', 'id'))
                            ->searchable()->preload(),

                        Forms\Components\TextInput::make('author_name')->label('اسم الكاتب')->maxLength(100),
                    ]),

                    Forms\Components\Select::make('brands')->label('الماركات المرتبطة')
                        ->multiple()
                        ->relationship('brands', 'name_ar')
                        ->searchable()->preload()
                        ->helperText('اربط المقال بماركة أو أكثر ليظهر في صفحتها'),

                    Forms\Components\Select::make('carModels')->label('الموديلات المرتبطة')
                        ->multiple()
                        ->relationship('carModels', 'name_ar')
                        ->searchable()->preload()
                        ->helperText('اربط المقال بموديل محدد ليظهر في صفحته'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('source_name')->label('المصدر')->maxLength(100),
                        Forms\Components\TextInput::make('source_url')->label('رابط المصدر')->url()->maxLength(255),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الصورة والإعدادات')->icon('heroicon-o-photo')->schema([
                    Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')
                        ->image()->disk($disk)->directory('news/covers')->visibility('private')->maxSize(5120),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('status')->label('الحالة')
                            ->options(['published' => 'منشور', 'draft' => 'مسودة', 'hidden' => 'مخفي'])
                            ->default('published'),
                        Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                        Forms\Components\Toggle::make('is_breaking')->label('عاجل 🔴')->inline(false),
                    ]),

                    Forms\Components\DateTimePicker::make('published_at')->label('تاريخ النشر')->nullable(),
                ]),

                Forms\Components\Tabs\Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('meta_title_ar')->label('Meta Title (عربي)'),
                        Forms\Components\TextInput::make('meta_title_en')->label('Meta Title (إنجليزي)'),
                        Forms\Components\Textarea::make('meta_description_ar')->label('Meta Description (عربي)')->rows(2),
                        Forms\Components\Textarea::make('meta_description_en')->label('Meta Description (إنجليزي)')->rows(2),
                    ]),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('الصورة')
                    ->disk(config('filesystems.default', 'public'))->width(70)->height(45)->extraImgAttributes(['class' => 'rounded object-cover']),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(40)->sortable(),
                Tables\Columns\TextColumn::make('category.name_ar')->label('التصنيف')->badge(),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'published', 'warning' => 'draft', 'secondary' => 'hidden'])
                    ->formatStateUsing(fn($state) => match($state) { 'published' => 'منشور', 'draft' => 'مسودة', 'hidden' => 'مخفي', default => $state }),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
                Tables\Columns\IconColumn::make('is_breaking')->label('عاجل')->boolean(),
                Tables\Columns\TextColumn::make('views_count')->label('المشاهدات')->sortable(),
                Tables\Columns\TextColumn::make('published_at')->label('النشر')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'draft' => 'مسودة', 'hidden' => 'مخفي']),
                Tables\Filters\SelectFilter::make('news_category_id')->label('التصنيف')->relationship('category', 'name_ar'),
                Tables\Filters\TernaryFilter::make('is_breaking')->label('عاجل'),
            ])
            ->actions([
                Tables\Actions\Action::make('publishTelegram')->label('نشر في تلجرام')
                    ->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(fn () => app(\App\Services\TelegramService::class)->isConfigured())
                    ->requiresConfirmation()
                    ->modalHeading('نشر الخبر في قناة تلجرام')
                    ->modalDescription('يُنشر في قسم الأخبار بالقناة.')
                    ->action(function (NewsArticle $record) {
                        if (! $record->cover_image_url) {
                            \Filament\Notifications\Notification::make()->title('أضف صورة غلاف للخبر أولاً')->danger()->send();
                            return;
                        }
                        $res = app(\App\Services\TelegramService::class)->sendPhoto(
                            $record->cover_image_url,
                            $record->telegramCaption(),
                            \App\Models\Setting::get('telegram_topic_id_news')
                        );
                        $res['ok']
                            ? \Filament\Notifications\Notification::make()->title('تم النشر في قسم الأخبار ✓')->success()->send()
                            : \Filament\Notifications\Notification::make()->title('فشل النشر')->body($res['error'] ?? '')->danger()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')->label('نشر المحدد')
                        ->action(fn($records) => $records->each->update(['status' => 'published', 'published_at' => now()])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit'   => Pages\EditNewsArticle::route('/{record}/edit'),
        ];
    }
}
