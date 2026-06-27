<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Models\SectionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BrandSectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';
    protected static ?string $title = 'أقسام الماركة';
    protected static ?string $label = 'قسم';

    public function form(Form $form): Form
    {
        $sectionTypes = SectionType::active()->orderBy('sort_order')
            ->get()->mapWithKeys(fn($t) => [$t->id => "{$t->default_icon} {$t->name_ar} ({$t->name_en})"]);

        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('section_type_id')->label('نوع القسم')
                    ->options($sectionTypes)->required()->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        $type = SectionType::find($state);
                        if ($type) {
                            $set('slug', $type->key);
                            $set('layout_type', $type->default_layout);
                            $set('icon', $type->default_icon);
                            $set('is_model_specific', $type->is_model_specific);
                        }
                    }),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('custom_name_ar')->label('اسم مخصص (عربي)')->helperText('اتركه فارغاً للاسم الافتراضي'),
                Forms\Components\TextInput::make('custom_name_en')->label('اسم مخصص (إنجليزي)'),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Textarea::make('custom_description_ar')->label('وصف مخصص (عربي)')->rows(2),
                Forms\Components\Textarea::make('custom_description_en')->label('وصف مخصص (إنجليزي)')->rows(2),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('icon')->label('الأيقونة (emoji)')->placeholder('🖼️'),
                Forms\Components\Select::make('layout_type')->label('طريقة العرض')
                    ->options(SectionType::layouts())->default('grid'),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),
            Forms\Components\FileUpload::make('cover_image')->label('صورة غلاف القسم (اختيارية)')
                ->image()->directory('brand-sections/covers')->columnSpanFull(),
            Forms\Components\Section::make('خيارات الظهور')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Toggle::make('is_enabled')->label('مفعّل')->default(true)->inline(false),
                    Forms\Components\Toggle::make('show_in_brand_home')->label('في صفحة الماركة')->default(true)->inline(false),
                    Forms\Components\Toggle::make('show_in_navigation')->label('في القائمة')->default(true)->inline(false),
                ]),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Toggle::make('show_in_homepage')->label('في الصفحة الرئيسية')->default(false)->inline(false),
                    Forms\Components\Toggle::make('is_model_specific')->label('خاص بالموديلات')->default(false)->inline(false),
                ]),
            ])->columns(1),
            Forms\Components\KeyValue::make('settings')->label('إعدادات إضافية (JSON)')->columnSpanFull()
                ->helperText('إعدادات خاصة بطريقة العرض مثل عدد العناصر في الصف'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('slug')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('الغلاف')
                    ->disk(config('filesystems.default', 'public'))->square()->size(44),
                Tables\Columns\TextColumn::make('icon')->label('الأيقونة')->getStateUsing(fn($record) => $record->getIcon()),
                Tables\Columns\TextColumn::make('sectionType.name_ar')->label('النوع')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('custom_name_ar')->label('الاسم المخصص')->placeholder('—'),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->badge()->color('gray')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('layout_type')->label('العرض')->badge(),
                Tables\Columns\IconColumn::make('is_enabled')->label('مفعّل')->boolean(),
                Tables\Columns\IconColumn::make('show_in_navigation')->label('القائمة')->boolean(),
                Tables\Columns\IconColumn::make('is_model_specific')->label('موديلات')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('ترتيب')->sortable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة قسم جديد')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
