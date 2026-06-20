<?php

namespace App\Filament\Resources;

use App\Models\Ad;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdsResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string { return 'الإعلانات'; }

    public static function getModelLabel(): string { return 'إعلان'; }

    public static function getPluralModelLabel(): string { return 'الإعلانات'; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_manage_ads');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('الاسم')->required(),

            Forms\Components\Select::make('position')
                ->label('الموقع')
                ->options([
                    'header' => 'رأس الصفحة',
                    'footer' => 'أسفل الصفحة',
                    'wallpaper_page_top' => 'أعلى صفحة الخلفية',
                    'wallpaper_page_bottom' => 'أسفل صفحة الخلفية',
                    'wallpaper_page_sidebar' => 'شريط جانبي',
                    'category_page' => 'صفحة الأقسام',
                    'homepage_banner' => 'بانر الرئيسية',
                    'search_page' => 'صفحة البحث',
                ])
                ->required(),

            Forms\Components\Select::make('type')
                ->label('النوع')
                ->options(['html' => 'كود HTML', 'image' => 'صورة'])
                ->live()
                ->required(),

            Forms\Components\Textarea::make('html_code')
                ->label('كود الإعلان (HTML)')
                ->rows(5)
                ->visible(fn(Forms\Get $get) => $get('type') === 'html'),

            Forms\Components\FileUpload::make('image_file')
                ->label('صورة الإعلان')
                ->image()
                ->disk('r2')
                ->directory('ads')
                ->visibility('public')
                ->visible(fn(Forms\Get $get) => $get('type') === 'image'),

            Forms\Components\TextInput::make('link_url')->label('رابط الإعلان')->url(),

            Forms\Components\Select::make('language')
                ->label('اللغة')
                ->options(['ar' => 'عربي', 'en' => 'إنجليزي', 'both' => 'كلاهما'])
                ->default('both'),

            Forms\Components\Toggle::make('is_active')->label('مفعل')->default(true),

            Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),

            Forms\Components\DateTimePicker::make('starts_at')->label('تاريخ البداية')->nullable(),
            Forms\Components\DateTimePicker::make('ends_at')->label('تاريخ الانتهاء')->nullable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('position')->label('الموقع'),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('language')->label('اللغة'),
                Tables\Columns\IconColumn::make('is_active')->label('مفعل')->boolean(),
                Tables\Columns\TextColumn::make('impressions_count')->label('مشاهدات'),
                Tables\Columns\TextColumn::make('clicks_count')->label('نقرات'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\AdsResource\Pages\ListAds::route('/'),
            'create' => \App\Filament\Resources\AdsResource\Pages\CreateAd::route('/create'),
            'edit' => \App\Filament\Resources\AdsResource\Pages\EditAd::route('/{record}/edit'),
        ];
    }
}
