<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WallpapersRelationManager extends RelationManager
{
    protected static string $relationship = 'wallpaperContent';
    protected static ?string $title = 'الخلفيات';
    protected static ?string $modelLabel = 'خلفية';
    protected static ?string $pluralModelLabel = 'الخلفيات';
    protected static ?string $icon = 'heroicon-o-photo';

    /** The brand's enabled wallpapers section id (content_items require it). */
    protected function sectionId(): ?int
    {
        return $this->getOwnerRecord()->sectionByKey('wallpapers')?->id;
    }

    protected function collectionOptions(): array
    {
        return ContentCollection::where('brand_id', $this->getOwnerRecord()->id)
            ->whereNull('car_model_id')
            ->where('is_active', true)->orderBy('sort_order')
            ->get()->mapWithKeys(fn($c) => [$c->id => ($c->icon ? $c->icon . ' ' : '') . $c->name_ar])
            ->toArray();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title_ar')->label('العنوان (اختياري)')
                ->maxLength(255)->placeholder('يُترك فارغاً = بدون اسم'),
            Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                ->options(fn() => $this->collectionOptions())
                ->searchable()->nullable()->placeholder('بدون قسم فرعي'),
            Forms\Components\FileUpload::make('image_path')->label('الصورة')
                ->image()->directory('content-items/images')->required()->columnSpanFull(),
            Forms\Components\Select::make('status')->label('الحالة')
                ->options(['published' => 'منشور', 'draft' => 'مسودة'])->default('published'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_ar')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('')
                    ->disk(config('filesystems.default', 'public'))->square()->size(56),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->placeholder('—')->limit(30),
                Tables\Columns\TextColumn::make('collection.name_ar')->label('القسم الفرعي')->badge()->color('warning')->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'published', 'gray' => 'draft']),
                Tables\Columns\TextColumn::make('downloads_count')->label('تحميلات')->sortable(),
                Tables\Columns\TextColumn::make('likes_count')->label('إعجابات')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('content_collection_id')->label('القسم الفرعي')
                    ->options(fn() => $this->collectionOptions()),
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['published' => 'منشور', 'draft' => 'مسودة']),
            ])
            ->headerActions([
                // ─── Bulk multi-image upload ───────────────────────────────────
                Tables\Actions\Action::make('bulkUpload')
                    ->label('رفع جماعي')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->modalHeading('رفع عدة خلفيات دفعة واحدة')
                    ->modalSubmitActionLabel('رفع الكل')
                    ->form([
                        Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي (لكل الصور)')
                            ->options(fn() => $this->collectionOptions())
                            ->searchable()->nullable()->placeholder('بدون قسم فرعي')
                            ->helperText('أنشئ الأقسام الفرعية من تبويب "الأقسام الفرعية"'),
                        Forms\Components\FileUpload::make('images')->label('الصور')
                            ->image()->multiple()->reorderable()
                            ->directory('content-items/images')->required()
                            ->helperText('اسحب أو اختر عدة صور دفعة واحدة'),
                    ])
                    ->action(function (array $data) {
                        $sectionId = $this->sectionId();
                        if (!$sectionId) {
                            Notification::make()->title('فعّل قسم "الخلفيات" لهذه الماركة أولاً')->danger()->send();
                            return;
                        }
                        $brandId = $this->getOwnerRecord()->id;
                        $count = 0;
                        foreach (($data['images'] ?? []) as $path) {
                            ContentItem::create([
                                'brand_id'              => $brandId,
                                'brand_section_id'      => $sectionId,
                                'content_collection_id' => $data['content_collection_id'] ?? null,
                                'content_type'          => 'wallpapers',
                                'title_ar'              => '',
                                'image_path'            => $path,
                                'thumbnail_path'        => $path,
                                'file_path'             => $path,
                                'status'                => 'published',
                            ]);
                            $count++;
                        }
                        Notification::make()->title("تم رفع {$count} خلفية")->success()->send();
                    }),

                Tables\Actions\CreateAction::make()->label('إضافة خلفية')
                    ->disabled(fn() => $this->sectionId() === null)
                    ->tooltip(fn() => $this->sectionId() === null ? 'فعّل قسم الخلفيات أولاً' : null)
                    ->mutateFormDataUsing(function (array $data) {
                        $data['brand_section_id'] = $this->sectionId();
                        $data['content_type']     = 'wallpapers';
                        $data['thumbnail_path']   = $data['thumbnail_path'] ?? $data['image_path'] ?? null;
                        $data['file_path']        = $data['image_path'] ?? null;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('moveCollection')->label('نقل لقسم فرعي')
                        ->icon('heroicon-o-folder-arrow-down')
                        ->form([
                            Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                                ->options(fn() => $this->collectionOptions())->nullable()->placeholder('بدون قسم فرعي'),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['content_collection_id' => $data['content_collection_id'] ?? null])),
                    Tables\Actions\DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ]);
    }
}
