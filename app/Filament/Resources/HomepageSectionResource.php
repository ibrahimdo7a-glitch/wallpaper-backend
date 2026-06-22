<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomepageSectionResource\Pages;
use App\Models\HomepageSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HomepageSectionResource extends Resource
{
    protected static ?string $model = HomepageSection::class;
    protected static ?string $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Homepage Builder';
    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $modelLabel      = 'قسم الصفحة الرئيسية';
    protected static ?string $pluralModelLabel = 'Homepage Builder';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات القسم')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم الداخلي (للأدمن فقط)')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('نوع القسم')
                    ->options(HomepageSection::types())
                    ->required()
                    ->reactive(),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)'),
                    Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)'),
                    Forms\Components\TextInput::make('subtitle_ar')->label('الوصف (عربي)'),
                    Forms\Components\TextInput::make('subtitle_en')->label('الوصف (إنجليزي)'),
                ]),
            ]),

            Forms\Components\Section::make('إعدادات العرض')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('layout')
                        ->label('طريقة العرض')
                        ->options(HomepageSection::layouts())
                        ->default('grid')
                        ->required(),
                    Forms\Components\Select::make('visibility')
                        ->label('إظهار على')
                        ->options(['all' => 'الكل', 'desktop' => 'Desktop فقط', 'mobile' => 'Mobile فقط', 'tablet' => 'Tablet فقط'])
                        ->default('all')
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0),
                ]),
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
            ]),

            Forms\Components\Section::make('إعدادات خاصة بالنوع')
                ->description('تحكم في عدد العناصر وخيارات أخرى حسب نوع القسم')
                ->schema([
                    Forms\Components\KeyValue::make('settings')
                        ->label('الإعدادات (Key → Value)')
                        ->helperText('مثال: limit = 8 | featured_only = true | override_downloads = 320000')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable()->width(50),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('النوع')
                    ->colors([
                        'primary'   => 'hero',
                        'success'   => 'brands',
                        'warning'   => 'statistics',
                        'danger'    => 'news',
                        'secondary' => fn ($state) => true,
                    ])
                    ->formatStateUsing(fn ($state) => HomepageSection::types()[$state] ?? $state),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->limit(30),
                Tables\Columns\BadgeColumn::make('layout')->label('Layout')->color('gray'),
                Tables\Columns\BadgeColumn::make('visibility')->label('الجهاز')
                    ->colors(['primary' => 'all', 'success' => 'desktop', 'warning' => 'mobile', 'danger' => 'tablet']),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة'),
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
            'index'  => Pages\ListHomepageSections::route('/'),
            'create' => Pages\CreateHomepageSection::route('/create'),
            'edit'   => Pages\EditHomepageSection::route('/{record}/edit'),
        ];
    }
}
