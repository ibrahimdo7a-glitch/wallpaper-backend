<?php

namespace App\Filament\Resources\CarModelResource\RelationManagers;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModelWallpapersRelationManager extends RelationManager
{
    protected static string $relationship = 'wallpaperContent';
    protected static ?string $title = 'الخلفيات';
    protected static ?string $modelLabel = 'خلفية';
    protected static ?string $pluralModelLabel = 'الخلفيات';
    protected static ?string $icon = 'heroicon-o-photo';

    protected function brand()
    {
        return $this->getOwnerRecord()->brand;
    }

    /** The brand's enabled wallpapers section id. */
    protected function sectionId(): ?int
    {
        return $this->brand()?->sectionByKey('wallpapers')?->id;
    }

    /** Collections scoped to THIS model (per-model sub-sections). */
    protected function collectionOptions(): array
    {
        $model = $this->getOwnerRecord();
        return ContentCollection::where('brand_id', $model->brand_id)
            ->where('car_model_id', $model->id)
            ->where('is_active', true)->orderBy('sort_order')
            ->get()->mapWithKeys(fn($c) => [$c->id => ($c->icon ? $c->icon . ' ' : '') . $c->name_ar])
            ->toArray();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title_ar')->label('العنوان (اختياري)')->maxLength(255),
            Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                ->options(fn() => $this->collectionOptions())->searchable()->nullable()
                ->placeholder('بدون قسم فرعي')
                ->helperText('أنشئ الأقسام الفرعية من تبويب "الأقسام الفرعية"'),
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
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['success' => 'published', 'gray' => 'draft']),
                Tables\Columns\TextColumn::make('downloads_count')->label('تحميلات')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('content_collection_id')->label('القسم الفرعي')->options(fn() => $this->collectionOptions()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('bulkUpload')
                    ->label('رفع جماعي')->icon('heroicon-o-arrow-up-tray')->color('success')
                    ->modalHeading('رفع عدة خلفيات لهذا الموديل')
                    ->modalSubmitActionLabel('رفع الكل')
                    ->form([
                        Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                            ->options(fn() => $this->collectionOptions())->searchable()->nullable()->placeholder('بدون قسم فرعي'),
                        Forms\Components\FileUpload::make('images')->label('الصور')
                            ->image()->multiple()->reorderable()->directory('content-items/images')->required()
                            ->helperText('اسحب عدة صور دفعة واحدة'),
                    ])
                    ->action(function (array $data) {
                        $sectionId = $this->sectionId();
                        if (!$sectionId) {
                            Notification::make()->title('فعّل قسم "الخلفيات" للماركة أولاً')->danger()->send();
                            return;
                        }
                        $model = $this->getOwnerRecord();
                        $count = 0;
                        foreach (($data['images'] ?? []) as $path) {
                            ContentItem::create([
                                'brand_id'              => $model->brand_id,
                                'brand_section_id'      => $sectionId,
                                'car_model_id'          => $model->id,
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
                        Notification::make()->title("تم رفع {$count} خلفية لهذا الموديل")->success()->send();
                    }),

                Tables\Actions\CreateAction::make()->label('إضافة خلفية')
                    ->disabled(fn() => $this->sectionId() === null)
                    ->tooltip(fn() => $this->sectionId() === null ? 'فعّل قسم الخلفيات للماركة أولاً' : null)
                    ->mutateFormDataUsing(function (array $data) {
                        $model = $this->getOwnerRecord();
                        $data['brand_id']         = $model->brand_id;
                        $data['brand_section_id'] = $this->sectionId();
                        $data['content_type']     = 'wallpapers';
                        $data['thumbnail_path']   = $data['thumbnail_path'] ?? $data['image_path'] ?? null;
                        $data['file_path']        = $data['image_path'] ?? null;
                        return $data;
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assignCollection')->label('تعيين لقسم فرعي')
                        ->icon('heroicon-o-folder-arrow-down')->color('warning')
                        ->modalHeading('تعيين الخلفيات المحددة لقسم فرعي')
                        ->form([
                            Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                                ->options(fn() => $this->collectionOptions())->nullable()->placeholder('بدون قسم فرعي'),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['content_collection_id' => $data['content_collection_id'] ?? null]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ]);
    }
}
