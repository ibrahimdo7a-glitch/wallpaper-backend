<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;
    protected static ?string $navigationIcon  = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'السيارات';
    protected static ?int    $navigationSort  = 1;

    public static function getNavigationLabel(): string  { return 'الماركات'; }
    public static function getModelLabel(): string       { return 'ماركة'; }
    public static function getPluralModelLabel(): string { return 'الماركات'; }

    public static function form(Form $form): Form
    {
        $disk = config('filesystems.default', 'public');

        return $form->schema([
            Forms\Components\Tabs::make()->tabs([

                Forms\Components\Tabs\Tab::make('المعلومات الأساسية')->icon('heroicon-o-information-circle')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')->required()->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                $context === 'create' ? $set('slug', Str::slug($state) ?: Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state))) : null
                            ),
                        Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->required()->maxLength(100),
                    ]),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true)->maxLength(100),
                        Forms\Components\TextInput::make('country')->label('الدولة')->placeholder('China')->maxLength(50),
                    ]),

                    Forms\Components\Grid::make(1)->schema([
                        Forms\Components\TextInput::make('website_url')->label('الموقع الرسمي')->url()->maxLength(255),
                    ]),

                    Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(3),
                    Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(3),
                ]),

                Forms\Components\Tabs\Tab::make('الصور')->icon('heroicon-o-photo')->schema([
                    Forms\Components\FileUpload::make('logo')->label('الشعار (Logo)')
                        ->image()->disk($disk)->directory('brands/logos')->visibility('public')
                        ->imagePreviewHeight('80')->maxSize(2048),
                    Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')
                        ->image()->disk($disk)->directory('brands/covers')->visibility('public')
                        ->maxSize(5120),
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
                Tables\Columns\ImageColumn::make('logo')->label('الشعار')
                    ->disk(config('filesystems.default', 'public'))->width(50)->height(50)->extraImgAttributes(['class' => 'rounded-lg object-contain']),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country')->label('الدولة'),
                Tables\Columns\TextColumn::make('models_count')->label('الموديلات')->sortable(),
                Tables\Columns\TextColumn::make('wallpapers_count')->label('الخلفيات')->sortable(),
                Tables\Columns\TextColumn::make('apps_count')->label('التطبيقات')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('المميز'),
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
                    Tables\Actions\BulkAction::make('activate')->label('تفعيل')->icon('heroicon-o-eye')
                        ->action(fn($records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivate')->label('إخفاء')->icon('heroicon-o-eye-slash')
                        ->action(fn($records) => $records->each->update(['is_active' => false])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
