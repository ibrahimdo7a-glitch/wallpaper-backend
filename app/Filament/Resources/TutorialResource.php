<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TutorialResource\Pages;
use App\Models\Tutorial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TutorialResource extends Resource
{
    protected static ?string $model = Tutorial::class;
    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?int    $navigationSort  = 30;

    public static function getNavigationLabel(): string  { return 'الشروحات'; }
    public static function getModelLabel(): string       { return 'شرح'; }
    public static function getPluralModelLabel(): string { return 'الشروحات والدروس'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make()->tabs([

                Forms\Components\Tabs\Tab::make('المحتوى')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, $context) =>
                                $context === 'create' ? $set('slug', Str::slug($state)) : null
                            ),
                        Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(255),
                    ]),
                    Forms\Components\TextInput::make('slug')->label('Slug')->required()->unique(ignoreRecord: true),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Textarea::make('summary_ar')->label('الملخص (عربي)')->rows(3),
                        Forms\Components\Textarea::make('summary_en')->label('الملخص (إنجليزي)')->rows(3),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\RichEditor::make('content_ar')->label('المحتوى (عربي)')->columnSpanFull(),
                        Forms\Components\RichEditor::make('content_en')->label('المحتوى (إنجليزي)')->columnSpanFull(),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الربط والإعدادات')->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('brand_id')->label('الماركة')
                            ->relationship('brand', 'name_ar')->searchable()->preload()->nullable(),
                        Forms\Components\Select::make('car_model_id')->label('الموديل')
                            ->relationship('carModel', 'name_ar')->searchable()->preload()->nullable(),
                    ]),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('difficulty')->label('الصعوبة')
                            ->options(['beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم'])
                            ->default('beginner'),
                        Forms\Components\TextInput::make('duration_label')->label('المدة (مثل: 10 دقائق)')->maxLength(50),
                        Forms\Components\Select::make('status')->label('الحالة')
                            ->options(['draft' => 'مسودة', 'published' => 'منشور', 'archived' => 'مؤرشف'])
                            ->default('draft'),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('video_url')->label('رابط الفيديو (YouTube)')->url()->nullable(),
                        Forms\Components\DateTimePicker::make('published_at')->label('تاريخ النشر'),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                    ]),
                ]),

                Forms\Components\Tabs\Tab::make('الصورة والـ SEO')->schema([
                    Forms\Components\FileUpload::make('cover_image')->label('صورة الغلاف')
                        ->image()->disk(config('filesystems.default', 'public'))->directory('tutorials/covers')
                        ->maxSize(5120),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('meta_title')->label('Meta Title'),
                        Forms\Components\TextInput::make('meta_description')->label('Meta Description'),
                    ]),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('')->disk('r2')->square(),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('brand.name_ar')->label('الماركة')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('carModel.name_ar')->label('الموديل')->badge()->color('gray'),
                Tables\Columns\BadgeColumn::make('difficulty')->label('الصعوبة')
                    ->colors(['success' => 'beginner', 'warning' => 'intermediate', 'danger' => 'advanced'])
                    ->formatStateUsing(fn($state) => match($state) {
                        'beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم', default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['gray' => 'draft', 'success' => 'published', 'danger' => 'archived'])
                    ->formatStateUsing(fn($state) => match($state) {
                        'draft' => 'مسودة', 'published' => 'منشور', 'archived' => 'مؤرشف', default => $state,
                    }),
                Tables\Columns\TextColumn::make('views_count')->label('المشاهدات')->sortable(),
                Tables\Columns\TextColumn::make('published_at')->label('النشر')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')->label('الماركة')
                    ->relationship('brand', 'name_ar'),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['draft' => 'مسودة', 'published' => 'منشور', 'archived' => 'مؤرشف']),
                Tables\Filters\SelectFilter::make('difficulty')->label('الصعوبة')
                    ->options(['beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('نشر المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn($records) => $records->each->update(['status' => 'published', 'published_at' => now()])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTutorials::route('/'),
            'create' => Pages\CreateTutorial::route('/create'),
            'edit'   => Pages\EditTutorial::route('/{record}/edit'),
        ];
    }
}
