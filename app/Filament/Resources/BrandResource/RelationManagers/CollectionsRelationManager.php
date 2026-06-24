<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Models\BrandSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CollectionsRelationManager extends RelationManager
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
                    ->required()->maxLength(100)
                    ->placeholder('مثال: ليوبارد 5 / خلفيات قطر'),
                Forms\Components\TextInput::make('name_en')->label('الاسم (إنجليزي)')->maxLength(100),
            ]),

            Forms\Components\Select::make('brand_section_id')->label('تابع لقسم (اختياري)')
                ->options(fn() => BrandSection::where('brand_id', $this->ownerRecord->id)
                    ->where('is_enabled', true)->with('sectionType')->get()
                    ->mapWithKeys(fn($s) => [$s->id => $s->getIcon() . ' ' . $s->getNameAr()]))
                ->searchable()->nullable()
                ->helperText('اربط المجموعة بقسم معيّن مثل "الخلفيات"، أو اتركها عامة للماركة'),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('icon')->label('أيقونة / علم')
                    ->placeholder('🇶🇦 أو 🚗'),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(2),
                Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(2),
            ]),

            Forms\Components\FileUpload::make('image_path')->label('صورة الغلاف (اختياري)')
                ->image()->directory('collections')->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')->label('مفعّلة')->default(true)->inline(false),
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
                Tables\Columns\TextColumn::make('brandSection.slug')->label('القسم')->badge()->color('success')->placeholder('عام'),
                Tables\Columns\TextColumn::make('content_items_count')->label('العناصر')->counts('contentItems')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّلة')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة مجموعة')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
