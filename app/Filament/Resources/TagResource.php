<?php

namespace App\Filament\Resources;

use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string { return 'الوسوم'; }

    public static function getModelLabel(): string { return 'وسم'; }

    public static function getPluralModelLabel(): string { return 'الوسوم'; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_manage_tags');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name_ar')
                ->label('الاسم (عربي)')
                ->required()
                ->live(onBlur: true),

            Forms\Components\TextInput::make('name_en')
                ->label('الاسم (إنجليزي)')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn(Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(Tag::class, 'slug', ignoreRecord: true),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_ar')->label('عربي')->searchable(),
                Tables\Columns\TextColumn::make('name_en')->label('إنجليزي')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug'),
                Tables\Columns\TextColumn::make('wallpapers_count')->label('الخلفيات')->sortable(),
            ])
            ->defaultSort('wallpapers_count', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\TagResource\Pages\ListTags::route('/'),
            'create' => \App\Filament\Resources\TagResource\Pages\CreateTag::route('/create'),
            'edit' => \App\Filament\Resources\TagResource\Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
