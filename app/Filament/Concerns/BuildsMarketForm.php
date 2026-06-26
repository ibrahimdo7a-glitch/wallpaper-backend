<?php

namespace App\Filament\Concerns;

use App\Models\MarketCategory;
use App\Models\MarketField;
use App\Models\MarketListing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Shared form + table for the car and parts marketplaces.
 * Each resource implements scope() = 'cars' | 'parts'.
 */
trait BuildsMarketForm
{
    abstract public static function scope(): string;

    protected static function gulfCountries(): array
    {
        return ['قطر' => 'قطر', 'السعودية' => 'السعودية', 'الإمارات' => 'الإمارات', 'الكويت' => 'الكويت', 'البحرين' => 'البحرين', 'عُمان' => 'عُمان'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            static::aiSection(),
            static::primarySection(),
            static::priceSection(),
            static::dynamicSpecsSection(),
            static::catalogSection(),
            static::locationSection(),
            static::imagesSection(),
            static::contactSection(),
            static::publishSection(),
        ]);
    }

    protected static function aiSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('🤖 الذكاء الاصطناعي')
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
            ]);
    }

    protected static function primarySection(): Forms\Components\Section
    {
        $scope = static::scope();

        $schema = [];

        if ($scope === 'cars') {
            $schema[] = Forms\Components\Select::make('listing_type')->label('نوع الإعلان')
                ->options(['car_sale' => '🚗 سيارة للبيع', 'car_request' => '🔎 طلب سيارة'])
                ->required()->live()->default('car_sale');
        } else {
            $schema[] = Forms\Components\Select::make('market_category_id')->label('القسم')
                ->options(fn () => MarketCategory::active()->pluck('name_ar', 'id'))
                ->required()->live()->searchable()->preload()
                ->helperText('اختر القسم وتظهر حقوله تلقائياً. الأقسام والحقول تُدار من «الأقسام والحقول».');
            $schema[] = Forms\Components\Hidden::make('listing_type')->default('part');
        }

        $schema[] = Forms\Components\Grid::make(2)->schema([
            Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(200),
            Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(200),
        ]);
        $schema[] = Forms\Components\Grid::make(2)->schema([
            Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(4),
            Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(4),
        ]);

        return Forms\Components\Section::make('النوع والتفاصيل')->schema($schema);
    }

    protected static function priceSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('السعر')->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('price')->label('السعر')->numeric()->nullable(),
                Forms\Components\Select::make('currency')->label('العملة')
                    ->options(['QAR' => 'ريال قطري', 'SAR' => 'ريال سعودي', 'AED' => 'درهم إماراتي', 'KWD' => 'دينار كويتي', 'BHD' => 'دينار بحريني', 'OMR' => 'ريال عُماني', 'USD' => 'دولار'])
                    ->default('QAR'),
                Forms\Components\Toggle::make('is_negotiable')->label('قابل للتفاوض')->inline(false),
            ]),
            Forms\Components\Toggle::make('is_paid_listing')->label('إعلان مدفوع 💰 (وإلا مجاني)')->inline(false)
                ->helperText('تتحكم: هل هذا الإعلان مدفوع أو مجاني'),
        ]);
    }

    /** Dynamic fields rendered from market_fields (curated for cars, custom for the chosen parts section). */
    protected static function dynamicSpecsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('المواصفات')
            ->schema(fn (Forms\Get $get) => static::buildDynamicFields($get));
    }

    protected static function buildDynamicFields(Forms\Get $get): array
    {
        $scope = static::scope();
        $categoryId = $scope === 'parts' ? ($get('market_category_id') ? (int) $get('market_category_id') : null) : null;

        $fields = MarketField::forContext($scope, $categoryId)->where('is_enabled', true)->get();

        if ($fields->isEmpty()) {
            return [Forms\Components\Placeholder::make('no_fields')->label('')->content(
                $scope === 'parts'
                    ? 'اختر القسم لعرض حقوله، أو أضف حقولاً من «الأقسام والحقول».'
                    : 'لا توجد حقول مفعّلة. فعّلها من «إعدادات السوق».'
            )];
        }

        return [Forms\Components\Grid::make(2)->schema($fields->map(fn (MarketField $f) => static::renderField($f))->all())];
    }

    protected static function renderField(MarketField $f): Forms\Components\Component
    {
        $path  = $f->formPath();
        $label = $f->label_ar . ($f->unit ? " ({$f->unit})" : '');

        $c = match ($f->type) {
            'number'   => Forms\Components\TextInput::make($path)->numeric(),
            'select'   => Forms\Components\Select::make($path)->options($f->optionsMap())->native(false),
            'boolean'  => Forms\Components\Toggle::make($path)->inline(false),
            'textarea' => Forms\Components\Textarea::make($path)->rows(3)->columnSpanFull(),
            default    => Forms\Components\TextInput::make($path)->maxLength(255),
        };

        $c = $c->label($label);
        if ($f->placeholder) { $c = $c->placeholder($f->placeholder); }
        if ($f->help_text)   { $c = $c->helperText($f->help_text); }
        if ($f->is_required) { $c = $c->required(); }

        return $c;
    }

    protected static function catalogSection(): Forms\Components\Section
    {
        $isCars = static::scope() === 'cars';

        return Forms\Components\Section::make($isCars ? 'ماركة وموديل السيارة' : 'التوافق (اختياري)')
            ->description($isCars ? null : 'الماركة/الموديل المتوافق مع القطعة')
            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('brand_id')->label('الماركة')
                        ->relationship('brand', 'name_ar')->searchable()->preload()->nullable(),
                    Forms\Components\Select::make('car_model_id')->label('الموديل')
                        ->relationship('carModel', 'name_ar')->searchable()->preload()->nullable(),
                ]),
            ]);
    }

    protected static function locationSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('الموقع')->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('country')->label('الدولة')
                    ->options(static::gulfCountries())->searchable()->default('قطر'),
                Forms\Components\TextInput::make('city')->label('المدينة')->maxLength(80),
            ]),
        ]);
    }

    protected static function imagesSection(): Forms\Components\Section
    {
        $disk = config('filesystems.default', 'public');

        return Forms\Components\Section::make('الصور')->schema([
            Forms\Components\FileUpload::make('images')->label('صور الإعلان')
                ->image()->multiple()->reorderable()
                ->disk($disk)->directory('market')->visibility('private')
                ->maxSize(5120)->maxFiles(10)->columnSpanFull()
                ->helperText('أول صورة = الغلاف. حتى ١٠ صور.'),
        ]);
    }

    protected static function contactSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('التواصل')->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('contact_name')->label('اسم المُعلِن')->maxLength(80),
                Forms\Components\TextInput::make('contact_phone')->label('رقم الهاتف')->tel()->maxLength(30),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('contact_whatsapp')->label('واتساب')->tel()->maxLength(30)
                    ->helperText('بصيغة دولية بدون + أو 00، مثال: 974XXXXXXXX'),
                Forms\Components\TextInput::make('contact_telegram')->label('تلجرام (يوزر)')->maxLength(60)->placeholder('@username'),
            ]),
        ]);
    }

    protected static function publishSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('النشر')->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'pending' => 'بانتظار المراجعة', 'sold' => 'مُباع', 'hidden' => 'مخفي'])
                    ->default('published'),
                Forms\Components\Toggle::make('is_featured')->label('مميّز ⭐')->inline(false),
                Forms\Components\DateTimePicker::make('expires_at')->label('ينتهي في (اختياري)')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isCars = static::scope() === 'cars';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_url')->label('')->square()->size(56),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(35)->sortable(),
                $isCars
                    ? Tables\Columns\TextColumn::make('listing_type')->label('النوع')->badge()
                        ->formatStateUsing(fn ($state) => $state === 'car_request' ? 'طلب' : 'للبيع')
                    : Tables\Columns\TextColumn::make('category.name_ar')->label('القسم')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('price')->label('السعر')
                    ->formatStateUsing(fn ($state, $record) => $state ? number_format($state, 0) . ' ' . $record->currency : '—'),
                Tables\Columns\TextColumn::make('city')->label('المدينة')->toggleable(),
                Tables\Columns\IconColumn::make('is_paid_listing')->label('مدفوع')->boolean(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->colors(['success' => 'published', 'warning' => 'pending', 'gray' => 'sold', 'danger' => 'hidden'])
                    ->formatStateUsing(fn ($state) => match ($state) { 'published' => 'منشور', 'pending' => 'مراجعة', 'sold' => 'مُباع', 'hidden' => 'مخفي', default => $state }),
                Tables\Columns\TextColumn::make('views_count')->label('👁')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
}
