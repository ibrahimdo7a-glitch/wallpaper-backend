<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketCategoryResource\Pages;
use App\Models\MarketCategory;
use App\Models\MarketListing;
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
    protected static ?string $slug = 'market-categories';
    protected static ?string $navigationIcon  = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'السوق';
    protected static ?int    $navigationSort   = 2;

    public static function getNavigationLabel(): string  { return 'التصنيفات'; }
    public static function getModelLabel(): string       { return 'تصنيف'; }
    public static function getPluralModelLabel(): string { return 'تصنيفات السوق'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->description('التصنيف تبويب فرعي داخل نوع الإعلان (مثلاً: بطاريات، إطارات، شواحن). يساعد الزائر يفلتر — اختياري.')
                ->schema([
                    Forms\Components\Select::make('listing_type')->label('يتبع لأي نوع')
                        ->options(MarketListing::types())->required()->default('part')
                        ->helperText('التصنيف يظهر فقط للإعلانات من هذا النوع'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')
                            ->required()->maxLength(120)->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')
                            ->maxLength(120),
                    ]),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('slug')->label('Slug')
                            ->required()->maxLength(140)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('icon')->label('أيقونة (إيموجي)')
                            ->maxLength(8)->placeholder('🔋'),
                        Forms\Components\TextInput::make('sort_order')->label('الترتيب')
                            ->numeric()->default(0),
                    ]),

                    Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label('')->width('1%'),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('listing_type')->label('النوع')->badge()
                    ->formatStateUsing(fn ($state) => MarketListing::types()[$state] ?? $state),
                Tables\Columns\TextColumn::make('listings_count')->label('عدد الإعلانات')
                    ->counts('listings')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('listing_type')->label('النوع')
                    ->options(MarketListing::types()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
