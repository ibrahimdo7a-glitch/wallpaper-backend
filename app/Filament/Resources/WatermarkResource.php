<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WatermarkResource\Pages;
use App\Models\Watermark;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WatermarkResource extends Resource
{
    protected static ?string $model = Watermark::class;

    protected static ?string $navigationIcon = 'heroicon-o-stamp';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'التواقيع';
    }

    public static function getModelLabel(): string
    {
        return 'توقيع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'التواقيع';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات التوقيع')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('اسم التوقيع')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('type')
                        ->label('نوع التوقيع')
                        ->options([
                            'text' => 'نص',
                            'image' => 'صورة',
                            'combined' => 'نص + صورة',
                        ])
                        ->required()
                        ->live(),
                ])->columns(2),

            Forms\Components\Section::make('إعدادات النص')
                ->schema([
                    Forms\Components\TextInput::make('text_ar')
                        ->label('النص (عربي)'),

                    Forms\Components\TextInput::make('text_en')
                        ->label('النص (إنجليزي)'),

                    Forms\Components\TextInput::make('font_family')
                        ->label('نوع الخط')
                        ->default('Arial'),

                    Forms\Components\TextInput::make('font_size')
                        ->label('حجم الخط')
                        ->numeric()
                        ->default(24)
                        ->minValue(8)
                        ->maxValue(200),

                    Forms\Components\ColorPicker::make('font_color')
                        ->label('لون الخط')
                        ->default('#FFFFFF'),
                ])
                ->columns(2)
                ->visible(fn(Forms\Get $get) => in_array($get('type'), ['text', 'combined'])),

            Forms\Components\Section::make('إعدادات الصورة')
                ->schema([
                    Forms\Components\FileUpload::make('image_file')
                        ->label('صورة التوقيع (PNG شفاف)')
                        ->image()
                        ->disk('r2')
                        ->directory('watermarks')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/png', 'image/webp']),
                ])
                ->visible(fn(Forms\Get $get) => in_array($get('type'), ['image', 'combined'])),

            Forms\Components\Section::make('الموضع والمظهر')
                ->schema([
                    Forms\Components\Select::make('position')
                        ->label('موضع التوقيع')
                        ->options([
                            'top-left' => 'أعلى يسار',
                            'top-center' => 'أعلى وسط',
                            'top-right' => 'أعلى يمين',
                            'middle-left' => 'وسط يسار',
                            'center' => 'المنتصف',
                            'middle-right' => 'وسط يمين',
                            'bottom-left' => 'أسفل يسار',
                            'bottom-center' => 'أسفل وسط',
                            'bottom-right' => 'أسفل يمين',
                        ])
                        ->default('bottom-right'),

                    Forms\Components\TextInput::make('opacity')
                        ->label('الشفافية %')
                        ->numeric()
                        ->default(70)
                        ->minValue(10)
                        ->maxValue(100),

                    Forms\Components\TextInput::make('margin_x')
                        ->label('الهامش الأفقي (px)')
                        ->numeric()
                        ->default(20),

                    Forms\Components\TextInput::make('margin_y')
                        ->label('الهامش العمودي (px)')
                        ->numeric()
                        ->default(20),

                    Forms\Components\TextInput::make('scale')
                        ->label('الحجم %')
                        ->numeric()
                        ->default(100)
                        ->minValue(10)
                        ->maxValue(300),

                    Forms\Components\TextInput::make('rotation')
                        ->label('الدوران (درجة)')
                        ->numeric()
                        ->default(0)
                        ->minValue(-180)
                        ->maxValue(180),
                ])->columns(3),

            Forms\Components\Section::make('إعدادات الوصول')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('مفعل')
                        ->default(true),

                    Forms\Components\Toggle::make('is_default')
                        ->label('افتراضي'),

                    Forms\Components\Select::make('allowedRoles')
                        ->label('الأدوار المسموحة')
                        ->relationship('allowedRoles', 'name')
                        ->multiple()
                        ->preload()
                        ->placeholder('جميع الأدوار'),

                    Forms\Components\Select::make('allowedUsers')
                        ->label('المشرفون المسموحون')
                        ->relationship('allowedUsers', 'name')
                        ->multiple()
                        ->searchable()
                        ->placeholder('جميع المشرفين'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'text' => 'نص',
                        'image' => 'صورة',
                        'combined' => 'نص + صورة',
                    })
                    ->colors([
                        'primary' => 'text',
                        'success' => 'image',
                        'warning' => 'combined',
                    ]),

                Tables\Columns\TextColumn::make('position')
                    ->label('الموضع'),

                Tables\Columns\TextColumn::make('opacity')
                    ->label('الشفافية')
                    ->suffix('%'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('افتراضي')
                    ->boolean(),

                Tables\Columns\TextColumn::make('wallpapers_count')
                    ->label('الخلفيات')
                    ->counts('wallpapers'),
            ])
            ->actions([
                Tables\Actions\Action::make('set_default')
                    ->label('تعيين افتراضي')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn(Watermark $w) => ! $w->is_default && $w->is_active)
                    ->action(function (Watermark $watermark) {
                        $watermark->setAsDefault();
                        Notification::make()->title('تم تعيين التوقيع الافتراضي')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWatermarks::route('/'),
            'create' => Pages\CreateWatermark::route('/create'),
            'edit' => Pages\EditWatermark::route('/{record}/edit'),
        ];
    }
}
