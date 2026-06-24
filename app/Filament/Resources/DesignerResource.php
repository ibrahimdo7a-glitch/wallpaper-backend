<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DesignerResource\Pages;
use App\Models\Designer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DesignerResource extends Resource
{
    protected static ?string $model = Designer::class;
    protected static ?string $navigationIcon  = 'heroicon-o-paint-brush';
    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?int    $navigationSort  = 3;

    public static function getNavigationLabel(): string  { return 'المصمّمون'; }
    public static function getModelLabel(): string       { return 'مصمّم'; }
    public static function getPluralModelLabel(): string { return 'المصمّمون'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')->required()->maxLength(150),
                Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->maxLength(150),
                Forms\Components\FileUpload::make('avatar_path')->label('الصورة الشخصية')
                    ->image()->disk(config('filesystems.default', 'public'))->directory('designers')->maxSize(2048),
                Forms\Components\TextInput::make('telegram_url')->label('رابط التليجرام')->url(),
                Forms\Components\Textarea::make('bio_ar')->label('نبذة')->rows(2)->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_path')->label('')
                    ->disk(config('filesystems.default', 'public'))->circular()->size(40),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('content_items_count')->label('التصاميم')->counts('contentItems')->badge()->color('gray'),
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
            'index'  => Pages\ListDesigners::route('/'),
            'create' => Pages\CreateDesigner::route('/create'),
            'edit'   => Pages\EditDesigner::route('/{record}/edit'),
        ];
    }
}
