<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsSourceResource\Pages;
use App\Models\NewsSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsSourceResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;

    protected static ?string $model = NewsSource::class;
    protected static ?string $navigationIcon  = 'heroicon-o-rss';
    protected static ?string $navigationGroup = 'الأخبار';
    protected static ?int    $navigationSort  = 13;

    public static function getNavigationLabel(): string  { return 'مصادر الأخبار'; }
    public static function getModelLabel(): string       { return 'مصدر'; }
    public static function getPluralModelLabel(): string { return 'مصادر الأخبار'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('اسم المصدر')->required()->maxLength(120)
                ->placeholder('مثال: CarNewsChina'),
            Forms\Components\TextInput::make('url')->label('رابط الموقع أو خلاصة RSS')->required()->url()->maxLength(500)
                ->placeholder('https://example.com  أو  https://example.com/feed')
                ->helperText('الصق رابط الموقع وسيكتشف الخلاصة تلقائيًا، أو الصق رابط RSS مباشرة (أدق).'),
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
                Tables\Columns\TextColumn::make('name')->label('المصدر')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('url')->label('الرابط')->limit(45)->color('gray')->url(fn ($record) => $record->url, true),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\TextColumn::make('last_fetched_at')->label('آخر جلب')->dateTime('d/m/Y H:i')->placeholder('—'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('لا توجد مصادر بعد')
            ->emptyStateDescription('أضف مواقع الأخبار التي تتابعها، ثم اجلب منها من صفحة «جلب الأخبار».');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNewsSources::route('/'),
            'create' => Pages\CreateNewsSource::route('/create'),
            'edit'   => Pages\EditNewsSource::route('/{record}/edit'),
        ];
    }
}
