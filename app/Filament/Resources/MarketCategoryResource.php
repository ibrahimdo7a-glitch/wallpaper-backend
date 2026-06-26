<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketCategoryResource\Pages;
use App\Filament\Resources\MarketCategoryResource\RelationManagers\FieldsRelationManager;
use App\Models\MarketCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MarketCategoryResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;

    protected static ?string $model = MarketCategory::class;
    protected static ?string $slug = 'market-sections';
    protected static ?string $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'سوق القطع والاكسسوارات';
    protected static ?int    $navigationSort   = 2;

    public static function getNavigationLabel(): string  { return 'الأقسام والحقول'; }
    public static function getModelLabel(): string       { return 'قسم'; }
    public static function getPluralModelLabel(): string { return 'أقسام السوق'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->description('القسم = تبويب في سوق القطع (مثلاً: ادبترات، فلاتر، دواليب، كشافات، خدمات). بعد إنشاء القسم افتحه (تعديل) لإضافة حقوله الخاصة من جدول «الحقول».')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name_ar')->label('اسم القسم (عربي)')
                            ->required()->maxLength(120)->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set, $context) => $context === 'create'
                                ? $set('slug', Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', (string) $state)))
                                : null),
                        Forms\Components\TextInput::make('name_en')->label('اسم القسم (إنجليزي)')->maxLength(120),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('slug')->label('Slug')->required()->maxLength(140)->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('icon')->label('أيقونة (إيموجي)')->maxLength(8)->placeholder('🔧'),
                        Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                    ]),
                    Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true),
                    Forms\Components\Hidden::make('listing_type')->default('part'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label('')->width('1%'),
                Tables\Columns\TextColumn::make('name_ar')->label('القسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('fields_count')->label('عدد الحقول')->counts('fields')->badge()->color('info'),
                Tables\Columns\TextColumn::make('listings_count')->label('عدد الإعلانات')->counts('listings')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('الحقول / تعديل'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [FieldsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMarketCategories::route('/'),
            'create' => Pages\CreateMarketCategory::route('/create'),
            'edit'   => Pages\EditMarketCategory::route('/{record}/edit'),
        ];
    }
}
