<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationItemResource extends Resource
{
    protected static ?string $model = NavigationItem::class;
    protected static ?string $navigationIcon  = 'heroicon-o-bars-3';
    protected static ?string $navigationLabel = 'القائمة الرئيسية';
    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $modelLabel      = 'رابط';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('label_ar')->label('الاسم (عربي)')->required(),
                Forms\Components\TextInput::make('label_en')->label('الاسم (إنجليزي)'),
                Forms\Components\TextInput::make('url')->label('الرابط')->required()->columnSpanFull(),
                Forms\Components\TextInput::make('icon')->label('الأيقونة (emoji أو heroicon)'),
                Forms\Components\Select::make('parent_id')
                    ->label('قائمة فرعية من')
                    ->options(NavigationItem::whereNull('parent_id')->pluck('label_ar', 'id'))
                    ->nullable(),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                Forms\Components\Toggle::make('open_in_new_tab')->label('فتح في تبويب جديد')->default(false)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('icon')->label('أيقونة'),
                Tables\Columns\TextColumn::make('label_ar')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('url')->label('الرابط')->limit(40),
                Tables\Columns\TextColumn::make('parent.label_ar')->label('تابع لـ')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit'   => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }
}
