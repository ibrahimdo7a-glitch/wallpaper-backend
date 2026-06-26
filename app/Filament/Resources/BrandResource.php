<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Filament\Resources\BrandResource\RelationManagers;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BrandResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = Brand::class;
    protected static ?string $navigationIcon  = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'السيارات والماركات';
    protected static ?int    $navigationSort  = 1;

    public static function getNavigationLabel(): string  { return 'الماركات'; }
    public static function getModelLabel(): string       { return 'ماركة'; }
    public static function getPluralModelLabel(): string { return 'الماركات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make()->tabs([

                Forms\Components\Tabs\Tab::make('بيانات الماركة')->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')->required()->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                $context === 'create' ? $set('slug', Str::slug($state)) : null
                            ),
                        Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->maxLength(100),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('country')->label('الدولة')->placeholder('الصين'),
                        Forms\Components\TextInput::make('website_url')->label('الموقع الرسمي')->url(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(4),
                        Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(4),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الصور')->icon('heroicon-o-photo')->schema([
                    Forms\Components\FileUpload::make('logo')->label('شعار الماركة')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                        ->disk(config('filesystems.default', 'public'))->directory('brands/logos')
                        ->maxSize(2048)
                        ->helperText('300×300 px — PNG شفاف مفضل'),
                    Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->disk(config('filesystems.default', 'public'))->directory('brands/covers')
                        ->maxSize(5120)
                        ->helperText('1920×600 px'),
                ]),

                Forms\Components\Tabs\Tab::make('الإعدادات')->icon('heroicon-o-cog')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                        Forms\Components\Toggle::make('is_featured')->label('مميزة')->inline(false),
                        Forms\Components\Toggle::make('maintenance_mode')->label('🚧 وضع الصيانة')->inline(false)
                            ->helperText('يخفي الماركة مؤقتاً عن الزوار'),
                    ]),
                    Forms\Components\TextInput::make('sort_order')->label('ترتيب الظهور')->numeric()->default(0),
                ]),

                Forms\Components\Tabs\Tab::make('الهوية والألوان')->icon('heroicon-o-paint-brush')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\ColorPicker::make('primary_color')->label('اللون الأساسي'),
                        Forms\Components\ColorPicker::make('accent_color')->label('لون التمييز'),
                    ]),
                    Forms\Components\Section::make('روابط التواصل والـ CTA')->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('telegram_url')->label('تليجرام')->url()->placeholder('https://t.me/...'),
                            Forms\Components\TextInput::make('whatsapp_url')->label('واتساب')->url()->placeholder('https://wa.me/...'),
                            Forms\Components\TextInput::make('channel_url')->label('القناة')->url(),
                            Forms\Components\TextInput::make('download_cta_url')->label('رابط زر التحميل')->url(),
                        ]),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('download_cta_label_ar')->label('نص الزر (عربي)')->placeholder('تحميل التطبيقات'),
                            Forms\Components\TextInput::make('download_cta_label_en')->label('نص الزر (إنجليزي)')->placeholder('Download Apps'),
                        ]),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('meta_title_ar')->label('Meta Title (عربي)')->maxLength(60),
                        Forms\Components\TextInput::make('meta_title_en')->label('Meta Title (إنجليزي)')->maxLength(60),
                        Forms\Components\Textarea::make('meta_description_ar')->label('Meta Description (عربي)')->rows(2)->maxLength(160),
                        Forms\Components\Textarea::make('meta_description_en')->label('Meta Description (إنجليزي)')->rows(2)->maxLength(160),
                    ]),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')->label('')->disk(config('filesystems.default', 'public'))->circular()->size(40),
                Tables\Columns\TextColumn::make('name_ar')->label('الماركة')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('name_en')->label('EN')->searchable()->color('gray'),
                Tables\Columns\TextColumn::make('country')->label('الدولة')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('sections_count')->label('أقسام')
                    ->counts('sections')->badge()->color('success'),
                Tables\Columns\TextColumn::make('models_count')->label('موديلات')->sortable()->badge()->color('primary'),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->label('مميزة')->boolean(),
                Tables\Columns\IconColumn::make('maintenance_mode')->label('🚧')->boolean()->trueColor('warning')->falseColor('gray'),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتيب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('المفعلة'),
                Tables\Filters\TernaryFilter::make('maintenance_mode')->label('وضع الصيانة'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn(Brand $r) => $r->is_active ? 'إخفاء' : 'تفعيل')
                    ->icon(fn(Brand $r) => $r->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn(Brand $r) => $r->is_active ? 'warning' : 'success')
                    ->action(fn(Brand $r) => $r->update(['is_active' => !$r->is_active])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')->label('تفعيل المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn($records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivate')->label('إخفاء المحدد')
                        ->icon('heroicon-o-eye-slash')->color('warning')
                        ->action(fn($records) => $records->each->update(['is_active' => false])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BrandSectionsRelationManager::class,
            RelationManagers\CollectionsRelationManager::class,
            RelationManagers\CarModelsRelationManager::class,
            RelationManagers\WallpapersRelationManager::class,
            RelationManagers\TutorialsRelationManager::class,
            RelationManagers\FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit'   => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
