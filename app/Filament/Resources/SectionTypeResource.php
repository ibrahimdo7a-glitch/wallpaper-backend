<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SectionTypeResource\Pages;
use App\Models\SectionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SectionTypeResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = SectionType::class;
    protected static ?string $navigationIcon  = 'heroicon-o-squares-plus';
    protected static ?string $navigationGroup = 'السيارات';
    protected static ?int    $navigationSort  = 5;

    public static function getNavigationLabel(): string  { return 'أنواع الأقسام'; }
    public static function getModelLabel(): string       { return 'نوع قسم'; }
    public static function getPluralModelLabel(): string { return 'أنواع الأقسام'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('key')->label('المفتاح (key)')->required()
                    ->unique(ignoreRecord: true)->maxLength(50)
                    ->helperText('مثال: wallpapers — يستخدم برمجياً، لا تغيره بعد الإنشاء')
                    ->alphaDash(),
                Forms\Components\TextInput::make('default_icon')->label('الأيقونة الافتراضية')
                    ->placeholder('🖼️')->maxLength(10),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')->required()->maxLength(100),
                Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->required()->maxLength(100),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(2),
                Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(2),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('default_layout')->label('طريقة العرض الافتراضية')
                    ->options(SectionType::layouts())->default('grid'),
                Forms\Components\Toggle::make('is_model_specific')->label('خاص بالموديلات')->inline(false),
                Forms\Components\Toggle::make('is_global')->label('عام لكل الماركات')->inline(false),
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
                Tables\Columns\TextColumn::make('default_icon')->label(''),
                Tables\Columns\TextColumn::make('key')->label('المفتاح')->badge()->color('gray')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم عربي')->searchable(),
                Tables\Columns\TextColumn::make('name_en')->label('الاسم إنجليزي')->searchable()->color('gray'),
                Tables\Columns\TextColumn::make('default_layout')->label('العرض')->badge(),
                Tables\Columns\IconColumn::make('is_model_specific')->label('موديلات')->boolean(),
                Tables\Columns\IconColumn::make('is_global')->label('عام')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتيب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSectionTypes::route('/'),
            'create' => Pages\CreateSectionType::route('/create'),
            'edit'   => Pages\EditSectionType::route('/{record}/edit'),
        ];
    }
}
