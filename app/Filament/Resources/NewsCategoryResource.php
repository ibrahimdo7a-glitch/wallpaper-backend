<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsCategoryResource\Pages;
use App\Models\NewsCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class NewsCategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;
    protected static ?string $navigationIcon  = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'الأخبار';
    protected static ?int    $navigationSort  = 11;

    public static function getNavigationLabel(): string  { return 'تصنيفات الأخبار'; }
    public static function getModelLabel(): string       { return 'تصنيف'; }
    public static function getPluralModelLabel(): string { return 'تصنيفات الأخبار'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')->required()->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                        $context === 'create' ? $set('slug', Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state)) ?: Str::slug($state)) : null
                    ),
                Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->maxLength(100),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
                Forms\Components\ColorPicker::make('color')->label('اللون'),
                Forms\Components\TextInput::make('icon')->label('أيقونة (emoji)')->placeholder('📰'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label(''),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable(),
                Tables\Columns\ColorColumn::make('color')->label('اللون'),
                Tables\Columns\TextColumn::make('articles_count')->label('المقالات')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNewsCategories::route('/'),
            'create' => Pages\CreateNewsCategory::route('/create'),
            'edit'   => Pages\EditNewsCategory::route('/{record}/edit'),
        ];
    }
}
