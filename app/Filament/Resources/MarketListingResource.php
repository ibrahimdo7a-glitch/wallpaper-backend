<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketListingResource\Pages;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MarketListingResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;

    protected static ?string $model = MarketListing::class;
    protected static ?string $slug = 'market';
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'السوق';
    protected static ?int    $navigationSort   = 1;

    public static function getNavigationLabel(): string  { return 'الإعلانات'; }
    public static function getModelLabel(): string       { return 'إعلان'; }
    public static function getPluralModelLabel(): string { return 'إعلانات السوق'; }

    private static function gulfCountries(): array
    {
        return ['قطر' => 'قطر', 'السعودية' => 'السعودية', 'الإمارات' => 'الإمارات', 'الكويت' => 'الكويت', 'البحرين' => 'البحرين', 'عُمان' => 'عُمان'];
    }

    private static function isCar(Forms\Get $get): bool
    {
        return in_array($get('listing_type'), ['car_sale', 'car_request'], true);
    }

    public static function form(Form $form): Form
    {
        $disk = config('filesystems.default', 'public');

        return $form->schema([
            // ─── AI helper ───
            Forms\Components\Section::make('🤖 الذكاء الاصطناعي')
                ->collapsible()->collapsed()
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('ai_translate')
                            ->label('✨ ترجم العربي → إنجليزي')
                            ->icon('heroicon-o-language')->color('primary')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $ai = app(\App\Services\AiService::class);
                                if (! $ai->isConfigured()) {
                                    Notification::make()->title('فعّل الذكاء وأضف المفتاح في إعدادات الموقع أولًا')->danger()->send();
                                    return;
                                }
                                $r = $ai->translate(['title' => $get('title_ar'), 'description' => $get('description_ar')]);
                                if (! $r) {
                                    Notification::make()->title('تعذّرت الترجمة')->body($ai->lastError ?? '')->danger()->send();
                                    return;
                                }
                                if (filled($r['title'] ?? null))       { $set('title_en', $r['title']); }
                                if (filled($r['description'] ?? null)) { $set('description_en', $r['description']); }
                                Notification::make()->title('تمت الترجمة ✓')->success()->send();
                            }),
                    ]),
                ]),

            Forms\Components\Section::make('النوع والتفاصيل')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('listing_type')->label('نوع الإعلان')
                        ->options(MarketListing::types())->required()->live()
                        ->default('car_sale'),
                    Forms\Components\Select::make('market_category_id')->label('التصنيف')
                        ->options(fn (Forms\Get $get) => MarketCategory::active()
                            ->where('listing_type', $get('listing_type'))->pluck('name_ar', 'id'))
                        ->searchable()->preload()->nullable(),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(200)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, $context) =>
                            $context === 'create'
                                ? $set('slug', Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state)) . '-' . Str::random(4))
                                : null),
                    Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(200),
                ]),
                Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(200),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(4),
                    Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(4),
                ]),
            ]),

            Forms\Components\Section::make('السعر والحالة')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('price')->label('السعر')->numeric()->nullable(),
                    Forms\Components\Select::make('currency')->label('العملة')
                        ->options(['QAR' => 'ريال قطري', 'SAR' => 'ريال سعودي', 'AED' => 'درهم إماراتي', 'KWD' => 'دينار كويتي', 'BHD' => 'دينار بحريني', 'OMR' => 'ريال عُماني', 'USD' => 'دولار'])
                        ->default('QAR'),
                    Forms\Components\Select::make('condition')->label('الحالة')
                        ->options(['new' => 'جديد', 'used' => 'مستعمل', 'na' => 'غير محدد'])->nullable(),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Toggle::make('is_negotiable')->label('السعر قابل للتفاوض')->inline(false),
                    Forms\Components\Toggle::make('is_paid_listing')->label('إعلان مدفوع 💰 (وإلا مجاني)')->inline(false)
                        ->helperText('تتحكم: هل هذا الإعلان مدفوع أو مجاني'),
                ]),
            ]),

            Forms\Components\Section::make('الموقع')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('country')->label('الدولة')
                        ->options(self::gulfCountries())->searchable()->default('قطر'),
                    Forms\Components\TextInput::make('city')->label('المدينة')->maxLength(80),
                ]),
            ]),

            Forms\Components\Section::make('الماركة / السيارة')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('brand_id')->label('الماركة')
                        ->relationship('brand', 'name_ar')->searchable()->preload()->nullable()
                        ->helperText('للقطع/الاكسسوارات: الماركة المتوافقة. للسيارة: ماركتها.'),
                    Forms\Components\Select::make('car_model_id')->label('الموديل')
                        ->relationship('carModel', 'name_ar')->searchable()->preload()->nullable(),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('year')->label('سنة الصنع')->numeric()
                        ->visible(fn (Forms\Get $get) => self::isCar($get)),
                    Forms\Components\TextInput::make('mileage')->label('الممشى (كم)')->numeric()
                        ->visible(fn (Forms\Get $get) => self::isCar($get)),
                ]),
            ]),

            Forms\Components\Section::make('الصور')->schema([
                Forms\Components\FileUpload::make('images')->label('صور الإعلان')
                    ->image()->multiple()->reorderable()
                    ->disk($disk)->directory('market')->visibility('private')
                    ->maxSize(5120)->maxFiles(10)->columnSpanFull()
                    ->helperText('أول صورة = الغلاف. حتى ١٠ صور.'),
            ]),

            Forms\Components\Section::make('التواصل')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('contact_name')->label('اسم المُعلِن')->maxLength(80),
                    Forms\Components\TextInput::make('contact_phone')->label('رقم الهاتف')->tel()->maxLength(30),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('contact_whatsapp')->label('واتساب')->tel()->maxLength(30)
                        ->helperText('بصيغة دولية بدون + أو 00، مثال: 974XXXXXXXX'),
                    Forms\Components\TextInput::make('contact_telegram')->label('تلجرام (يوزر)')->maxLength(60)->placeholder('@username'),
                ]),
            ]),

            Forms\Components\Section::make('النشر')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('status')->label('الحالة')
                        ->options(['published' => 'منشور', 'pending' => 'بانتظار المراجعة', 'sold' => 'مُباع', 'hidden' => 'مخفي'])
                        ->default('published'),
                    Forms\Components\Toggle::make('is_featured')->label('مميّز ⭐')->inline(false),
                    Forms\Components\DateTimePicker::make('expires_at')->label('ينتهي في (اختياري)')->nullable(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_url')->label('')->square()->size(56),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(35)->sortable(),
                Tables\Columns\BadgeColumn::make('listing_type')->label('النوع')
                    ->formatStateUsing(fn ($state) => MarketListing::types()[$state] ?? $state),
                Tables\Columns\TextColumn::make('price')->label('السعر')
                    ->formatStateUsing(fn ($state, $record) => $state ? number_format($state, 0) . ' ' . $record->currency : '—'),
                Tables\Columns\TextColumn::make('city')->label('المدينة')->toggleable(),
                Tables\Columns\IconColumn::make('is_paid_listing')->label('مدفوع')->boolean(),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'published', 'warning' => 'pending', 'gray' => 'sold', 'secondary' => 'hidden'])
                    ->formatStateUsing(fn ($state) => match ($state) { 'published' => 'منشور', 'pending' => 'مراجعة', 'sold' => 'مُباع', 'hidden' => 'مخفي', default => $state }),
                Tables\Columns\TextColumn::make('views_count')->label('👁')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('listing_type')->label('النوع')->options(MarketListing::types()),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'pending' => 'مراجعة', 'sold' => 'مُباع', 'hidden' => 'مخفي']),
                Tables\Filters\TernaryFilter::make('is_paid_listing')->label('مدفوع'),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_sold')
                    ->label('مُباع')->icon('heroicon-o-check-badge')->color('gray')
                    ->visible(fn (MarketListing $r) => $r->status !== 'sold')
                    ->action(fn (MarketListing $r) => $r->update(['status' => 'sold'])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMarketListings::route('/'),
            'create' => Pages\CreateMarketListing::route('/create'),
            'edit'   => Pages\EditMarketListing::route('/{record}/edit'),
        ];
    }
}
