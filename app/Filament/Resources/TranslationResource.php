<?php

namespace App\Filament\Resources;

use App\Models\Translation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string { return 'الترجمات'; }

    public static function getModelLabel(): string { return 'ترجمة'; }

    public static function getPluralModelLabel(): string { return 'الترجمات'; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_manage_translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->label('المفتاح')
                ->required()
                ->unique(Translation::class, 'key', ignoreRecord: true)
                ->disabledOn('edit'),

            Forms\Components\Select::make('group')
                ->label('المجموعة')
                ->options([
                    'general' => 'عام',
                    'navigation' => 'التنقل',
                    'actions' => 'الأفعال',
                    'wallpaper' => 'الخلفية',
                    'sort' => 'الترتيب',
                    'pages' => 'الصفحات',
                    'uploader' => 'المشرف',
                    'messages' => 'الرسائل',
                    'report' => 'البلاغات',
                ])
                ->required(),

            Forms\Components\Textarea::make('value_ar')
                ->label('النص (عربي)')
                ->required()
                ->rows(2),

            Forms\Components\Textarea::make('value_en')
                ->label('النص (إنجليزي)')
                ->required()
                ->rows(2),

            Forms\Components\TextInput::make('description')
                ->label('الوصف (للمراجع)')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('المفتاح')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('group')->label('المجموعة')->badge(),
                Tables\Columns\TextColumn::make('value_ar')->label('عربي')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('value_en')->label('إنجليزي')->limit(50)->searchable(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر تعديل')->since()->sortable(),
            ])
            ->defaultSort('group')
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('المجموعة')
                    ->options([
                        'general' => 'عام',
                        'navigation' => 'التنقل',
                        'actions' => 'الأفعال',
                        'wallpaper' => 'الخلفية',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => \App\Filament\Resources\TranslationResource\Pages\ListTranslations::route('/'),
            'create' => \App\Filament\Resources\TranslationResource\Pages\CreateTranslation::route('/create'),
            'edit' => \App\Filament\Resources\TranslationResource\Pages\EditTranslation::route('/{record}/edit'),
        ];
    }
}
