<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeroBannerResource\Pages;
use App\Models\HeroBanner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HeroBannerResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = HeroBanner::class;
    protected static ?string $navigationIcon  = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Hero Banners';
    protected static ?string $navigationGroup = 'الإعدادات والمظهر';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $modelLabel      = 'Hero Banner';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('النصوص')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('title_ar')->label('العنوان الكبير (عربي)')->required(),
                    Forms\Components\TextInput::make('title_en')->label('العنوان الكبير (إنجليزي)'),
                    Forms\Components\Textarea::make('subtitle_ar')->label('العنوان الثانوي (عربي)')->rows(2),
                    Forms\Components\Textarea::make('subtitle_en')->label('العنوان الثانوي (إنجليزي)')->rows(2),
                    Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(2),
                    Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(2),
                ]),
            ]),

            Forms\Components\Section::make('الصورة والألوان')->columns(2)->schema([
                Forms\Components\FileUpload::make('image_path')
                    ->label('صورة الـ Hero')
                    ->image()
                    ->disk(config('filesystems.default', 'public'))
                    ->directory('hero')
                    ->columnSpanFull(),
                Forms\Components\ColorPicker::make('bg_color')->label('لون الخلفية')->default('#000000'),
                Forms\Components\ColorPicker::make('text_color')->label('لون النص')->default('#ffffff'),
            ]),

            Forms\Components\Section::make('الأزرار')->columns(2)->schema([
                Forms\Components\TextInput::make('primary_btn_label_ar')->label('نص الزر الأول (عربي)'),
                Forms\Components\TextInput::make('primary_btn_label_en')->label('نص الزر الأول (إنجليزي)'),
                Forms\Components\TextInput::make('primary_btn_url')->label('رابط الزر الأول')->url(),
                Forms\Components\TextInput::make('secondary_btn_label_ar')->label('نص الزر الثاني (عربي)'),
                Forms\Components\TextInput::make('secondary_btn_label_en')->label('نص الزر الثاني (إنجليزي)'),
                Forms\Components\TextInput::make('secondary_btn_url')->label('رابط الزر الثاني')->url(),
            ]),

            Forms\Components\Section::make('الحالة')->columns(2)->schema([
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('الصورة')
                    ->disk(config('filesystems.default', 'public'))
                    ->width(80)->height(45),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->limit(40)->searchable(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHeroBanners::route('/'),
            'create' => Pages\CreateHeroBanner::route('/create'),
            'edit'   => Pages\EditHeroBanner::route('/{record}/edit'),
        ];
    }
}
