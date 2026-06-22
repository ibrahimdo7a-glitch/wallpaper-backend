<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Models\BrandSection;
use App\Models\CarModel;
use App\Models\SectionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContentItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'contentItems';
    protected static ?string $title = 'المحتوى';
    protected static ?string $label = 'عنصر محتوى';

    public function form(Form $form): Form
    {
        $brandId = $this->getOwnerRecord()->id;

        $sections = BrandSection::where('brand_id', $brandId)
            ->where('is_enabled', true)
            ->with('sectionType')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn($s) => [$s->id => $s->getIcon() . ' ' . $s->getNameAr()]);

        $models = CarModel::where('brand_id', $brandId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name_ar', 'id');

        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('brand_section_id')->label('القسم')->options($sections)
                    ->required()->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        $section = BrandSection::with('sectionType')->find($state);
                        if ($section) {
                            $set('content_type', $section->sectionType?->key);
                        }
                    }),
                Forms\Components\Select::make('car_model_id')->label('الموديل (اختياري)')
                    ->options($models)->searchable()->nullable()
                    ->placeholder('عام للماركة'),
            ]),
            Forms\Components\Hidden::make('content_type'),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(255),
                Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(255),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(3),
                Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(3),
            ]),

            Forms\Components\Section::make('الملفات والوسائط')->schema([
                Forms\Components\FileUpload::make('image_path')->label('الصورة الرئيسية')
                    ->image()->directory('content-items/images')->columnSpanFull(),
                Forms\Components\FileUpload::make('file_path')->label('ملف للتحميل')
                    ->directory('content-items/files')->maxSize(204800),
                Forms\Components\TextInput::make('video_url')->label('رابط الفيديو (YouTube)')->url()->placeholder('https://youtube.com/watch?v=...'),
                Forms\Components\TextInput::make('external_url')->label('رابط خارجي')->url(),
            ])->columns(2),

            Forms\Components\Section::make('بيانات إضافية (metadata)')->schema([
                Forms\Components\KeyValue::make('metadata')->label('بيانات خاصة بنوع المحتوى')
                    ->helperText('مثال: file_size, version, answer_ar, answer_en, link_type ...')
                    ->columnSpanFull(),
            ]),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'draft' => 'مسودة', 'archived' => 'مؤرشف'])
                    ->default('published'),
                Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                Forms\Components\Toggle::make('is_pinned')->label('مثبت')->inline(false),
            ]),
            Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_ar')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('')->disk(config('filesystems.default', 'public'))->square()->size(40),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('brandSection.slug')->label('القسم')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('content_type')->label('النوع')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('carModel.name_ar')->label('الموديل')->placeholder('عام'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'published', 'gray' => 'draft', 'danger' => 'archived']),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
                Tables\Columns\IconColumn::make('is_pinned')->label('مثبت')->boolean(),
                Tables\Columns\TextColumn::make('views_count')->label('مشاهدات')->sortable(),
                Tables\Columns\TextColumn::make('downloads_count')->label('تحميلات')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_section_id')->label('القسم')
                    ->options(fn() => BrandSection::where('brand_id', $this->getOwnerRecord()->id)
                        ->get()->mapWithKeys(fn($s) => [$s->id => $s->getNameAr()])),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'draft' => 'مسودة', 'archived' => 'مؤرشف']),
                Tables\Filters\TernaryFilter::make('is_featured')->label('المميز'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة محتوى')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')->label('نشر المحدد')
                        ->action(fn($records) => $records->each->update(['status' => 'published'])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
