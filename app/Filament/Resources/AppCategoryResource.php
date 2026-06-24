<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppCategoryResource\Pages;
use App\Models\AppCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AppCategoryResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = AppCategory::class;

    protected static ?string $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'التطبيقات';
    protected static ?int    $navigationSort  = 20;

    public static function getNavigationLabel(): string { return 'أقسام التطبيقات'; }
    public static function getModelLabel(): string      { return 'قسم'; }
    public static function getPluralModelLabel(): string{ return 'أقسام التطبيقات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                        $context === 'create'
                            ? $set('slug', Str::slug($state) ?: Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', $state)))
                            : null
                    ),

                Forms\Components\TextInput::make('name_en')
                    ->label('الاسم (إنجليزي)')
                    ->maxLength(100),

                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),

                Forms\Components\TextInput::make('icon')
                    ->label('أيقونة (emoji أو heroicon)')
                    ->placeholder('📱')
                    ->maxLength(50),

                Forms\Components\FileUpload::make('cover_image')
                    ->label('صورة الغلاف')
                    ->image()
                    ->disk(config('filesystems.default', 'public'))
                    ->directory('app-categories')
                    ->visibility('private'),

                Forms\Components\Toggle::make('is_active')
                    ->label('مفعّل')
                    ->default(true)
                    ->inline(false),

                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label('أيقونة'),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('apps_count')->label('التطبيقات')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('الترتيب')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAppCategories::route('/'),
            'create' => Pages\CreateAppCategory::route('/create'),
            'edit'   => Pages\EditAppCategory::route('/{record}/edit'),
        ];
    }
}
