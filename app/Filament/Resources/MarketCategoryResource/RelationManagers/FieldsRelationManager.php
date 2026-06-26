<?php

namespace App\Filament\Resources\MarketCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'حقول هذا القسم';

    protected static ?string $modelLabel = 'حقل';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('label_ar')->label('اسم الحقل (عربي)')
                    ->required()->maxLength(120)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set, $context) => $context === 'create'
                        ? $set('key', Str::slug(transliterator_transliterate('Any-Latin; Latin-ASCII', (string) $state), '_'))
                        : null),
                Forms\Components\TextInput::make('label_en')->label('اسم الحقل (إنجليزي)')->maxLength(120),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('key')->label('المفتاح (تلقائي)')
                    ->required()->maxLength(60)->alphaDash()
                    ->helperText('يُولَّد تلقائياً — لا تغيّره بعد إنشاء إعلانات'),
                Forms\Components\Select::make('type')->label('نوع الحقل')
                    ->options([
                        'text'     => 'نص',
                        'number'   => 'رقم',
                        'select'   => 'قائمة اختيارات',
                        'boolean'  => 'نعم / لا',
                        'textarea' => 'نص طويل',
                    ])->required()->default('text')->live(),
                Forms\Components\TextInput::make('unit')->label('الوحدة (اختياري)')->maxLength(20)->placeholder('مثل: مم، واط'),
            ]),

            Forms\Components\Repeater::make('options')->label('الخيارات')
                ->visible(fn (Forms\Get $get) => $get('type') === 'select')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('value')->label('القيمة (key)')->required()->alphaDash(),
                        Forms\Components\TextInput::make('label_ar')->label('النص المعروض')->required(),
                    ]),
                ])
                ->addActionLabel('إضافة خيار')->reorderable()->collapsible()->defaultItems(2),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('placeholder')->label('نص توضيحي (placeholder)')->maxLength(120),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Toggle::make('is_required')->label('مطلوب')->inline(false),
                Forms\Components\Toggle::make('is_filterable')->label('يظهر بالفلتر')->inline(false),
                Forms\Components\Toggle::make('is_enabled')->label('مفعّل')->default(true)->inline(false),
            ]),

            Forms\Components\Hidden::make('scope')->default('parts'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('label_ar')->label('الحقل')->weight('bold'),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'text' => 'نص', 'number' => 'رقم', 'select' => 'قائمة', 'boolean' => 'نعم/لا', 'textarea' => 'نص طويل', default => $state,
                    }),
                Tables\Columns\TextColumn::make('unit')->label('الوحدة')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_required')->label('مطلوب')->boolean(),
                Tables\Columns\IconColumn::make('is_filterable')->label('فلتر')->boolean(),
                Tables\Columns\IconColumn::make('is_enabled')->label('مفعّل')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة حقل')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['scope'] = 'parts';
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }
}
