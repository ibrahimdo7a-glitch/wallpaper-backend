<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarFileResource\Pages;
use App\Models\CarFile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CarFileResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = CarFile::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?int    $navigationSort  = 31;

    public static function getNavigationLabel(): string  { return 'الملفات'; }
    public static function getModelLabel(): string       { return 'ملف'; }
    public static function getPluralModelLabel(): string { return 'ملفات السيارات'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make()->tabs([

                Forms\Components\Tabs\Tab::make('المعلومات الأساسية')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                $context === 'create' ? $set('slug', Str::slug($state)) : null
                            ),
                        Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(255),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(3),
                        Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(3),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الملف والربط')->schema([
                    Forms\Components\FileUpload::make('file_path')->label('الملف')->required()
                        ->directory('car-files')->maxSize(102400),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('file_type')->label('نوع الملف (مثل: PDF, APK)')->maxLength(50),
                        Forms\Components\TextInput::make('version')->label('الإصدار')->maxLength(50),
                        Forms\Components\Select::make('status')->label('الحالة')
                            ->options(['draft' => 'مسودة', 'published' => 'منشور', 'archived' => 'مؤرشف'])
                            ->default('draft'),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('brand_id')->label('الماركة')
                            ->relationship('brand', 'name_ar')->searchable()->preload()->nullable(),
                        Forms\Components\Select::make('car_model_id')->label('الموديل')
                            ->relationship('carModel', 'name_ar')->searchable()->preload()->nullable(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                        Forms\Components\DateTimePicker::make('published_at')->label('تاريخ النشر'),
                    ]),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('brand.name_ar')->label('الماركة')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('carModel.name_ar')->label('الموديل')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('file_type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('version')->label('الإصدار'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['gray' => 'draft', 'success' => 'published', 'danger' => 'archived'])
                    ->formatStateUsing(fn($state) => match($state) {
                        'draft' => 'مسودة', 'published' => 'منشور', 'archived' => 'مؤرشف', default => $state,
                    }),
                Tables\Columns\TextColumn::make('downloads_count')->label('التحميلات')->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')->label('الماركة')
                    ->relationship('brand', 'name_ar'),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['draft' => 'مسودة', 'published' => 'منشور', 'archived' => 'مؤرشف']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCarFiles::route('/'),
            'create' => Pages\CreateCarFile::route('/create'),
            'edit'   => Pages\EditCarFile::route('/{record}/edit'),
        ];
    }
}
