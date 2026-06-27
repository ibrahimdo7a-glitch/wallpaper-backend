<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'الأقسام';
    }

    public static function getModelLabel(): string
    {
        return 'قسم';
    }

    public static function getPluralModelLabel(): string
    {
        return 'الأقسام';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات القسم')
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label('القسم الأب')
                        ->relationship('parent', 'name_ar')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->placeholder('قسم رئيسي'),

                    Forms\Components\TextInput::make('name_ar')
                        ->label('الاسم (عربي)')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                            // Arabic text doesn't produce slugs with Str::slug; fall back to English name
                            $slug = Str::slug($state ?? '');
                            if (empty($slug)) {
                                $slug = Str::slug($get('name_en') ?? '');
                            }
                            if (! empty($slug)) {
                                $set('slug', $slug);
                            }
                        }),

                    Forms\Components\TextInput::make('name_en')
                        ->label('الاسم (إنجليزي)')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                            // Auto-fill slug from English name when slug is still empty
                            if (empty($get('slug'))) {
                                $set('slug', Str::slug($state ?? ''));
                            }
                        }),

                    Forms\Components\Textarea::make('description_ar')
                        ->label('الوصف (عربي)')
                        ->rows(3),

                    Forms\Components\Textarea::make('description_en')
                        ->label('الوصف (إنجليزي)')
                        ->rows(3),
                ])->columns(2),

            Forms\Components\Section::make('الصورة والأيقونة')
                ->schema([
                    Forms\Components\FileUpload::make('cover_image')
                        ->label('صورة الغلاف')
                        ->image()
                        ->disk(config('filesystems.default', 'public'))
                        ->directory('categories')
                        ->visibility('private'),

                    Forms\Components\TextInput::make('icon')
                        ->label('أيقونة (CSS class أو emoji)')
                        ->maxLength(100),
                ])->columns(2),

            Forms\Components\Section::make('الإعدادات')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعل')
                        ->default(true),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('meta_title_ar')->label('Meta Title (عربي)'),
                    Forms\Components\TextInput::make('meta_title_en')->label('Meta Title (إنجليزي)'),
                    Forms\Components\Textarea::make('meta_description_ar')->label('Meta Description (عربي)')->rows(2),
                    Forms\Components\Textarea::make('meta_description_en')->label('Meta Description (إنجليزي)')->rows(2),
                ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('الصورة')
                    ->disk(config('filesystems.default', 'public'))
                    ->width(60)
                    ->height(40),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم')
                    ->searchable(),

                Tables\Columns\TextColumn::make('parent.name_ar')
                    ->label('القسم الأب')
                    ->placeholder('رئيسي'),

                Tables\Columns\TextColumn::make('wallpapers_count')
                    ->label('الخلفيات')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('مفعل'),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('القسم الأب')
                    ->relationship('parent', 'name_ar')
                    ->placeholder('الكل'),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
