<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarModelResource\Pages;
use App\Filament\Resources\CarModelResource\RelationManagers;
use App\Models\Brand;
use App\Models\CarModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CarModelResource extends Resource
{
    protected static ?string $model = CarModel::class;
    protected static ?string $navigationIcon  = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'السيارات';
    protected static ?int    $navigationSort  = 2;

    public static function getNavigationLabel(): string  { return 'الموديلات'; }
    public static function getModelLabel(): string       { return 'موديل'; }
    public static function getPluralModelLabel(): string { return 'الموديلات'; }

    public static function form(Form $form): Form
    {
        $disk = config('filesystems.default', 'public');

        return $form->schema([
            Forms\Components\Tabs::make()->tabs([

                Forms\Components\Tabs\Tab::make('المعلومات الأساسية')->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Select::make('brand_id')->label('الماركة')
                        ->options(Brand::active()->orderBy('sort_order')->pluck('name_ar', 'id'))
                        ->required()->searchable()->preload(),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name_ar')->label('اسم الموديل (عربي)')->required()->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                $context === 'create'
                                    ? $set('slug', Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state)) . '-' . Str::random(4))
                                    : null
                            ),
                        Forms\Components\TextInput::make('name_en')->label('اسم الموديل (إنجليزي)')->maxLength(150),
                    ]),

                    Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(150),

                    Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(3),
                    Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(3),

                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('year_from')->label('سنة الإصدار من')->numeric()->minValue(2010)->maxValue(2030),
                        Forms\Components\TextInput::make('year_to')->label('إلى')->numeric()->minValue(2010)->maxValue(2030),
                        Forms\Components\Select::make('car_type')->label('نوع السيارة')
                            ->options(['sedan' => 'سيدان', 'suv' => 'SUV', 'pickup' => 'بيك أب', 'van' => 'فان', 'coupe' => 'كوبيه']),
                        Forms\Components\Select::make('fuel_type')->label('نوع الوقود')
                            ->options(['electric' => 'كهربائية', 'hybrid' => 'هجينة', 'phev' => 'PHEV', 'petrol' => 'بنزين', 'diesel' => 'ديزل']),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الصور')->icon('heroicon-o-photo')->schema([
                    Forms\Components\FileUpload::make('image')->label('صورة الموديل')
                        ->image()->disk($disk)->directory('models/images')->visibility('private')->maxSize(5120),
                    Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')
                        ->image()->disk($disk)->directory('models/covers')->visibility('private')->maxSize(5120),
                ]),

                Forms\Components\Tabs\Tab::make('الإعدادات')->icon('heroicon-o-cog')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                        Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                        Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                    ]),
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
                Tables\Columns\ImageColumn::make('image')->label('الصورة')
                    ->disk(config('filesystems.default', 'public'))->width(60)->height(40)->extraImgAttributes(['class' => 'rounded object-cover']),
                Tables\Columns\TextColumn::make('brand.name_ar')->label('الماركة')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name_ar')->label('الموديل')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('year_from')->label('السنة'),
                Tables\Columns\TextColumn::make('fuel_type')->label('الوقود')->badge()
                    ->color(fn($state) => match($state) { 'electric' => 'success', 'hybrid' => 'info', 'phev' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('wallpapers_count')->label('خلفيات')->sortable(),
                Tables\Columns\TextColumn::make('apps_count')->label('تطبيقات')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')->label('الماركة')->relationship('brand', 'name_ar'),
                Tables\Filters\SelectFilter::make('fuel_type')->label('نوع الوقود')
                    ->options(['electric' => 'كهربائية', 'hybrid' => 'هجينة', 'phev' => 'PHEV', 'petrol' => 'بنزين']),
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('المميز'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn(CarModel $r) => $r->is_active ? 'إخفاء' : 'تفعيل')
                    ->icon(fn(CarModel $r) => $r->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn(CarModel $r) => $r->is_active ? 'warning' : 'success')
                    ->action(fn(CarModel $r) => $r->update(['is_active' => !$r->is_active])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ModelWallpapersRelationManager::class,
            RelationManagers\ModelTutorialsRelationManager::class,
            RelationManagers\ModelFilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCarModels::route('/'),
            'create' => Pages\CreateCarModel::route('/create'),
            'edit'   => Pages\EditCarModel::route('/{record}/edit'),
        ];
    }
}
