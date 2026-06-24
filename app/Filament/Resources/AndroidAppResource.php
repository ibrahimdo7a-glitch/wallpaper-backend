<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AndroidAppResource\Pages;
use App\Models\AndroidApp;
use App\Models\AppCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AndroidAppResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = AndroidApp::class;
    protected static ?string $navigationIcon  = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationGroup = 'التطبيقات';
    protected static ?int    $navigationSort  = 21;

    public static function getNavigationLabel(): string  { return 'التطبيقات'; }
    public static function getModelLabel(): string       { return 'تطبيق'; }
    public static function getPluralModelLabel(): string { return 'التطبيقات'; }

    public static function form(Form $form): Form
    {
        $disk = config('filesystems.default', 'public');

        return $form->schema([
            Forms\Components\Tabs::make()->tabs([

                // ─── معلومات أساسية ──────────────────────────────────────
                Forms\Components\Tabs\Tab::make('المعلومات الأساسية')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('title_ar')->label('اسم التطبيق (عربي)')
                                ->required()->maxLength(200)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                    $context === 'create'
                                        ? $set('slug', Str::slug($state) ?: Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state)) . '-' . Str::random(4))
                                        : null
                                ),
                            Forms\Components\TextInput::make('title_en')->label('اسم التطبيق (إنجليزي)')->maxLength(200),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('app_category_id')->label('القسم')
                                ->options(AppCategory::active()->orderBy('sort_order')->pluck('name_ar', 'id'))
                                ->searchable()->preload(),
                            Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(200),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(3),
                            Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(3),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Textarea::make('short_description_ar')->label('وصف قصير (عربي)')->rows(2),
                            Forms\Components\Textarea::make('short_description_en')->label('وصف قصير (إنجليزي)')->rows(2),
                        ]),
                    ]),

                // ─── الربط بالسيارات ─────────────────────────────────────
                Forms\Components\Tabs\Tab::make('ربط السيارات')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('brand_id')->label('الماركة')
                                ->relationship('brand', 'name_ar')->searchable()->preload()->nullable(),
                            Forms\Components\Select::make('car_model_id')->label('الموديل')
                                ->relationship('carModel', 'name_ar')->searchable()->preload()->nullable(),
                        ]),
                        Forms\Components\Select::make('importantForModels')->label('تطبيق مهم لهذه الموديلات')
                            ->multiple()->relationship('importantForModels', 'name_ar')->searchable()->preload(),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Toggle::make('works_on_car_screen')->label('يعمل على شاشة السيارة')->inline(false),
                            Forms\Components\Toggle::make('tested_on_car')->label('مجرّب على السيارة')->inline(false),
                            Forms\Components\Toggle::make('requires_login')->label('يتطلب تسجيل دخول')->inline(false),
                        ]),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Toggle::make('requires_internet')->label('يتطلب إنترنت')->inline(false),
                            Forms\Components\Toggle::make('is_important')->label('تطبيق مهم')->inline(false),
                            Forms\Components\Toggle::make('is_recommended')->label('موصى به')->inline(false),
                        ]),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Toggle::make('is_verified')->label('موثق')->inline(false),
                            Forms\Components\Toggle::make('show_on_home')->label('إظهار في الرئيسية')->inline(false),
                        ]),
                    ]),

                // ─── الأمان والتحقق ──────────────────────────────────────
                Forms\Components\Tabs\Tab::make('الأمان')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Forms\Components\Select::make('safety_status')->label('حالة الأمان')
                            ->options([
                                'verified'        => '✅ موثق - آمن ومجرّب',
                                'tested'          => '🔵 مجرّب - تم الاختبار',
                                'external_source' => '⚠️ مصدر خارجي - لم يُختبر',
                                'not_tested'      => '❓ غير مختبر',
                            ])
                            ->default('not_tested')
                            ->helperText('هذه الحالة تظهر للمستخدمين قبل تحميل التطبيق'),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('apk_sha256')->label('SHA256 Checksum للـ APK')
                                ->maxLength(64)->helperText('يُحسب تلقائياً عند رفع الملف إن أمكن'),
                            Forms\Components\TextInput::make('developer_name')->label('اسم المطور')->maxLength(150),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('language')->label('لغة التطبيق')->placeholder('عربي / English / 中文')->maxLength(100),
                            Forms\Components\TextInput::make('play_store_url')->label('رابط Play Store')->url()->nullable(),
                        ]),
                        Forms\Components\TextInput::make('official_website_url')->label('الموقع الرسمي')->url()->nullable(),
                    ]),

                // ─── الملفات والروابط ────────────────────────────────────
                Forms\Components\Tabs\Tab::make('الملفات والروابط')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        Forms\Components\FileUpload::make('icon_file')->label('أيقونة التطبيق')
                            ->image()->disk($disk)->directory('apps/icons')->visibility('private')
                            ->maxSize(2048)->imagePreviewHeight('80'),
                        Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')
                            ->image()->disk($disk)->directory('apps/covers')->visibility('private')->maxSize(5120),
                        Forms\Components\FileUpload::make('apk_file')->label('ملف APK')
                            ->disk($disk)->directory('apps/apk')->visibility('private')
                            ->acceptedFileTypes(['application/vnd.android.package-archive', 'application/octet-stream'])
                            ->maxSize(512000)->helperText('الحد الأقصى 500 MB'),
                        Forms\Components\TextInput::make('external_url')->label('رابط خارجي (Play Store أو موقع)')
                            ->url()->placeholder('https://play.google.com/store/apps/...')
                            ->helperText('اتركه فارغاً إذا رفعت ملف APK'),
                    ]),

                // ─── تفاصيل التطبيق ──────────────────────────────────────
                Forms\Components\Tabs\Tab::make('تفاصيل التطبيق')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('version')->label('الإصدار')->placeholder('2.1.4')->maxLength(30),
                            Forms\Components\TextInput::make('min_android')->label('الحد الأدنى لـ Android')->placeholder('Android 8.0+')->maxLength(50),
                            Forms\Components\TextInput::make('developer')->label('المطور')->maxLength(100),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('package_name')->label('Package Name')->placeholder('com.example.app')->maxLength(200),
                            Forms\Components\TextInput::make('file_size')->label('حجم الملف (bytes)')->numeric(),
                        ]),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('status')->label('الحالة')
                                ->options(['published' => 'منشور', 'pending' => 'قيد الانتظار', 'hidden' => 'مخفي'])
                                ->default('published'),
                            Forms\Components\Toggle::make('is_free')->label('مجاني')->default(true)->inline(false),
                            Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                        ]),
                    ]),

                // ─── لقطات الشاشة ────────────────────────────────────────
                Forms\Components\Tabs\Tab::make('لقطات الشاشة')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Forms\Components\Repeater::make('screenshots')
                            ->relationship()
                            ->label('لقطات شاشة التطبيق')
                            ->orderColumn('sort_order')
                            ->schema([
                                Forms\Components\FileUpload::make('image_file')->label('الصورة')
                                    ->image()->disk($disk)->directory('apps/screenshots')->visibility('private')
                                    ->required()->maxSize(5120),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('caption_ar')->label('تعليق (عربي)')->maxLength(200),
                                    Forms\Components\TextInput::make('caption_en')->label('تعليق (إنجليزي)')->maxLength(200),
                                ]),
                            ])
                            ->addActionLabel('إضافة لقطة شاشة')
                            ->columns(1),
                    ]),

                // ─── خطوات التنصيب ───────────────────────────────────────
                Forms\Components\Tabs\Tab::make('خطوات التنصيب')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Forms\Components\Repeater::make('installationSteps')
                            ->relationship()
                            ->label('خطوات التنصيب (حد أقصى ٦ خطوات)')
                            ->maxItems(6)
                            ->orderColumn('step_number')
                            ->schema([
                                Forms\Components\Hidden::make('step_number')->default(1),
                                Forms\Components\FileUpload::make('image_file')->label('صورة الخطوة')
                                    ->image()->disk($disk)->directory('apps/steps')->visibility('private')
                                    ->required()->maxSize(5120),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('title_ar')->label('عنوان الخطوة (عربي)')->maxLength(150),
                                    Forms\Components\TextInput::make('title_en')->label('عنوان الخطوة (إنجليزي)')->maxLength(150),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Textarea::make('description_ar')->label('وصف الخطوة (عربي)')->rows(2),
                                    Forms\Components\Textarea::make('description_en')->label('وصف الخطوة (إنجليزي)')->rows(2),
                                ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('إضافة خطوة')
                            ->helperText('أضف حتى ٦ صور لشرح خطوات التنصيب بالترتيب'),
                    ]),

                // ─── SEO ─────────────────────────────────────────────────
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
                Tables\Columns\ImageColumn::make('icon_file')->label('أيقونة')
                    ->disk(config('filesystems.default', 'public'))->width(50)->height(50)
                    ->extraImgAttributes(['class' => 'rounded-xl']),
                Tables\Columns\TextColumn::make('title_ar')->label('الاسم')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('category.name_ar')->label('القسم'),
                Tables\Columns\TextColumn::make('brand.name_ar')->label('الماركة')->badge()->color('primary'),
                Tables\Columns\BadgeColumn::make('safety_status')->label('الأمان')
                    ->colors([
                        'success' => 'verified',
                        'primary' => 'tested',
                        'warning' => 'external_source',
                        'secondary' => 'not_tested',
                    ])
                    ->formatStateUsing(fn($state) => match($state) {
                        'verified'        => '✅ موثق',
                        'tested'          => '🔵 مجرّب',
                        'external_source' => '⚠️ خارجي',
                        'not_tested'      => '❓ غير مختبر',
                        default           => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('version')->label('الإصدار'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'published', 'warning' => 'pending', 'secondary' => 'hidden'])
                    ->formatStateUsing(fn($state) => match($state) {
                        'published' => 'منشور', 'pending' => 'انتظار', 'hidden' => 'مخفي', default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
                Tables\Columns\IconColumn::make('works_on_car_screen')->label('شاشة سيارة')->boolean(),
                Tables\Columns\TextColumn::make('downloads_count')->label('التحميلات')->sortable()->numeric(),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'pending' => 'انتظار', 'hidden' => 'مخفي']),
                Tables\Filters\SelectFilter::make('app_category_id')->label('القسم')
                    ->relationship('category', 'name_ar'),
                Tables\Filters\SelectFilter::make('brand_id')->label('الماركة')
                    ->relationship('brand', 'name_ar'),
                Tables\Filters\SelectFilter::make('safety_status')->label('الأمان')
                    ->options(['verified' => 'موثق', 'tested' => 'مجرّب', 'external_source' => 'خارجي', 'not_tested' => 'غير مختبر']),
                Tables\Filters\TernaryFilter::make('works_on_car_screen')->label('يعمل على شاشة السيارة'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn(AndroidApp $r) => $r->status === 'published' ? 'إخفاء' : 'نشر')
                    ->icon(fn(AndroidApp $r) => $r->status === 'published' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn(AndroidApp $r) => $r->status === 'published' ? 'warning' : 'success')
                    ->action(function (AndroidApp $record) {
                        $record->update([
                            'status'       => $record->status === 'published' ? 'hidden' : 'published',
                            'published_at' => $record->status !== 'published' ? now() : $record->published_at,
                        ]);
                        Notification::make()->title('تم تحديث الحالة')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAndroidApps::route('/'),
            'create' => Pages\CreateAndroidApp::route('/create'),
            'edit'   => Pages\EditAndroidApp::route('/{record}/edit'),
        ];
    }
}
