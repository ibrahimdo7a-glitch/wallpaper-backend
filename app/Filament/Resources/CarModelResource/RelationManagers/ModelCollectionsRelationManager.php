<?php

namespace App\Filament\Resources\CarModelResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModelCollectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'collections';
    protected static ?string $title = 'الأقسام الفرعية';
    protected static ?string $modelLabel = 'قسم فرعي';
    protected static ?string $pluralModelLabel = 'الأقسام الفرعية';
    protected static ?string $icon = 'heroicon-o-folder';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name_ar')->label('الاسم (عربي)')
                    ->required()->maxLength(100)->placeholder('مثال: ليلية / رياضية / 4K'),
                Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->maxLength(100),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('icon')->label('أيقونة / علم')->placeholder('🌙'),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),
            Forms\Components\Textarea::make('description_ar')->label('الوصف (اختياري)')->rows(2),
            Forms\Components\FileUpload::make('image_path')->label('صورة الغلاف (اختياري)')
                ->image()->directory('collections')->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable()->width(50),
                Tables\Columns\ImageColumn::make('image_path')->label('')
                    ->disk(config('filesystems.default', 'public'))->square()->size(36),
                Tables\Columns\TextColumn::make('icon')->label('أيقونة'),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('content_items_count')->label('العناصر')->counts('contentItems')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة قسم فرعي')
                    ->mutateFormDataUsing(function (array $data) {
                        // collection belongs to this model's brand (car_model_id auto-set by relationship)
                        $data['brand_id'] = $this->getOwnerRecord()->brand_id;
                        return $data;
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => ! auth()->user()?->hasRole('مبدع')),
            ])]);
    }
}
