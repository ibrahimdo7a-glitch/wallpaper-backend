<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentItemResource\Pages;
use App\Models\Brand;
use App\Models\BrandSection;
use App\Models\CarModel;
use App\Models\ContentCollection;
use App\Models\ContentItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContentItemResource extends Resource
{
    protected static ?string $model = ContentItem::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'السيارات';
    protected static ?int    $navigationSort  = 6;

    public static function getNavigationLabel(): string  { return 'المحتوى'; }
    public static function getModelLabel(): string       { return 'عنصر'; }
    public static function getPluralModelLabel(): string { return 'المحتوى'; }

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('الربط والتصنيف')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('brand_id')->label('الماركة')
                        ->options(Brand::active()->orderBy('sort_order')->pluck('name_ar', 'id'))
                        ->required()->searchable()->live()
                        ->afterStateUpdated(fn(Forms\Set $set) => $set('brand_section_id', null)),

                    Forms\Components\Select::make('brand_section_id')->label('القسم')
                        ->options(fn(Forms\Get $get) => BrandSection::where('brand_id', $get('brand_id'))
                            ->where('is_enabled', true)->with('sectionType')->get()
                            ->mapWithKeys(fn($s) => [$s->id => $s->getIcon() . ' ' . $s->getNameAr()]))
                        ->required()->searchable()->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $section = BrandSection::with('sectionType')->find($state);
                            if ($section) $set('content_type', $section->sectionType?->key);
                        }),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Select::make('content_collection_id')->label('المجموعة (قسم فرعي)')
                        ->options(fn(Forms\Get $get) => ContentCollection::where('brand_id', $get('brand_id'))
                            ->where('is_active', true)->orderBy('sort_order')
                            ->get()->mapWithKeys(fn($c) => [$c->id => ($c->icon ? $c->icon . ' ' : '') . $c->name_ar]))
                        ->searchable()->nullable()->placeholder('بدون مجموعة')
                        ->helperText('مثل: ليوبارد 5 / خلفيات قطر')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name_ar')->label('اسم المجموعة (عربي)')->required(),
                            Forms\Components\TextInput::make('name_en')->label('اسم المجموعة (إنجليزي)'),
                            Forms\Components\TextInput::make('icon')->label('أيقونة / علم')->placeholder('🇶🇦'),
                        ])
                        ->createOptionUsing(function (array $data, Forms\Get $get) {
                            return ContentCollection::create([
                                'brand_id'         => $get('brand_id'),
                                'brand_section_id' => $get('brand_section_id'),
                                'name_ar'          => $data['name_ar'],
                                'name_en'          => $data['name_en'] ?? null,
                                'icon'             => $data['icon'] ?? null,
                            ])->id;
                        }),

                    Forms\Components\Select::make('car_model_id')->label('الموديل (اختياري)')
                        ->options(fn(Forms\Get $get) => CarModel::where('brand_id', $get('brand_id'))
                            ->where('is_active', true)->pluck('name_ar', 'id'))
                        ->searchable()->nullable()->placeholder('عام للماركة'),
                ]),
                Forms\Components\TextInput::make('content_type')->label('نوع المحتوى')->disabled()->dehydrated(),
            ]),

            Forms\Components\Section::make('المحتوى')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('title_ar')->label('العنوان (عربي)')->required()->maxLength(255),
                    Forms\Components\TextInput::make('title_en')->label('العنوان (إنجليزي)')->maxLength(255),
                ]),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\Textarea::make('description_ar')->label('الوصف (عربي)')->rows(3),
                    Forms\Components\Textarea::make('description_en')->label('الوصف (إنجليزي)')->rows(3),
                ]),
                Forms\Components\TextInput::make('author_name')->label('اسم المصمم / المصدر')
                    ->placeholder('مثال: تصميم فريق ليوبارد')->maxLength(255),
            ]),

            Forms\Components\Section::make('الوسائط والملفات')->columns(2)->schema([
                Forms\Components\FileUpload::make('image_path')->label('الصورة / الغلاف')
                    ->image()->directory('content-items/images')->columnSpanFull(),
                Forms\Components\FileUpload::make('thumbnail_path')->label('الصورة المصغرة')
                    ->image()->directory('content-items/thumbnails'),
                Forms\Components\FileUpload::make('file_path')->label('ملف للتحميل')
                    ->directory('content-items/files')->maxSize(512000),
                Forms\Components\TextInput::make('video_url')->label('رابط الفيديو (YouTube/Vimeo)')
                    ->url()->placeholder('https://youtube.com/watch?v=...')->columnSpanFull(),
                Forms\Components\TextInput::make('external_url')->label('رابط خارجي (للروابط المهمة)')
                    ->url()->columnSpanFull(),
            ]),

            Forms\Components\Section::make('بيانات إضافية')->schema([
                Forms\Components\KeyValue::make('metadata')
                    ->label('metadata (بيانات خاصة بالنوع)')
                    ->helperText('للـ FAQs: answer_ar, answer_en | للملفات: file_size, version, sha256 | للتطبيقات: version, safety_status, package_name')
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('الإعدادات')->columns(3)->schema([
                Forms\Components\Select::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'draft' => 'مسودة', 'archived' => 'مؤرشف'])
                    ->default('published'),
                Forms\Components\Toggle::make('is_featured')->label('مميز')->inline(false),
                Forms\Components\Toggle::make('is_pinned')->label('مثبت في الأعلى')->inline(false),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                Forms\Components\DateTimePicker::make('published_at')->label('تاريخ النشر'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('')->disk(config('filesystems.default', 'public'))->square()->size(40),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(35)->weight('bold'),
                Tables\Columns\TextColumn::make('brand.name_ar')->label('الماركة')->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('brandSection.slug')->label('القسم')->badge()->color('success'),
                Tables\Columns\TextColumn::make('collection.name_ar')->label('المجموعة')->badge()->color('warning')->placeholder('—'),
                Tables\Columns\TextColumn::make('content_type')->label('النوع')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('carModel.name_ar')->label('الموديل')->placeholder('عام')->color('gray'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'published', 'gray' => 'draft', 'danger' => 'archived']),
                Tables\Columns\IconColumn::make('is_featured')->label('مميز')->boolean(),
                Tables\Columns\IconColumn::make('is_pinned')->label('مثبت')->boolean(),
                Tables\Columns\TextColumn::make('views_count')->label('مشاهدات')->sortable(),
                Tables\Columns\TextColumn::make('downloads_count')->label('تحميلات')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')->label('الماركة')
                    ->options(Brand::orderBy('sort_order')->pluck('name_ar', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('content_type')->label('النوع')
                    ->options(\App\Models\SectionType::pluck('name_ar', 'key')),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'draft' => 'مسودة', 'archived' => 'مؤرشف']),
                Tables\Filters\TernaryFilter::make('is_featured')->label('المميز'),
                Tables\Filters\TernaryFilter::make('is_pinned')->label('المثبت'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')->label('نشر المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn($records) => $records->each->update(['status' => 'published', 'published_at' => now()])),
                    Tables\Actions\BulkAction::make('archive')->label('أرشفة المحدد')
                        ->icon('heroicon-o-archive-box')->color('warning')
                        ->action(fn($records) => $records->each->update(['status' => 'archived'])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContentItems::route('/'),
            'create' => Pages\CreateContentItem::route('/create'),
            'edit'   => Pages\EditContentItem::route('/{record}/edit'),
        ];
    }
}
