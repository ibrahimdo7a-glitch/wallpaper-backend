<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CarModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'carModels';
    protected static ?string $title = 'الموديلات';
    protected static ?string $label = 'موديل';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')->required()->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                        $context === 'create' ? $set('slug', Str::slug($state)) : null
                    ),
                Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->maxLength(150),
            ]),
            Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(table: 'car_models', ignoreRecord: true),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(2),
                Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(2),
            ]),
            Forms\Components\Grid::make(4)->schema([
                Forms\Components\Select::make('car_type')->label('نوع السيارة')
                    ->options(['sedan' => 'سيدان', 'suv' => 'SUV', 'van' => 'فان', 'hatchback' => 'هاتشباك', 'wagon' => 'واجن', 'pickup' => 'بيك أب']),
                Forms\Components\Select::make('fuel_type')->label('نوع الوقود')
                    ->options(['electric' => 'كهربائي', 'hybrid' => 'هجين', 'phev' => 'PHEV', 'petrol' => 'بنزين']),
                Forms\Components\TextInput::make('year_from')->label('من سنة')->numeric()->minValue(2000)->maxValue(2030),
                Forms\Components\TextInput::make('year_to')->label('إلى سنة')->numeric()->minValue(2000)->maxValue(2030)->placeholder('حتى الآن'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\FileUpload::make('image')->label('صورة الموديل')->image()->directory('car-models/images'),
                Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')->image()->directory('car-models/covers'),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('')->disk(config('filesystems.default', 'public'))->square()->size(40),
                Tables\Columns\TextColumn::make('name_ar')->label('الموديل')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('name_en')->label('EN')->color('gray'),
                Tables\Columns\BadgeColumn::make('fuel_type')->label('الوقود')
                    ->colors(['success' => 'electric', 'primary' => 'hybrid', 'warning' => 'phev', 'secondary' => 'petrol']),
                Tables\Columns\TextColumn::make('year_from')->label('السنة'),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة موديل')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
