<?php

namespace App\Filament\Resources\CarModelResource\RelationManagers;

use App\Filament\Concerns\InteractsWithContentWatermark;
use App\Models\CarModel;
use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\Designer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModelWallpapersRelationManager extends RelationManager
{
    use InteractsWithContentWatermark;

    protected static string $relationship = 'wallpaperContent';
    protected static ?string $title = 'الخلفيات';
    protected static ?string $modelLabel = 'خلفية';
    protected static ?string $pluralModelLabel = 'الخلفيات';
    protected static ?string $icon = 'heroicon-o-photo';

    // Per-request memo so the inline SelectColumns don't re-query options for every row (was ~96 queries/page).
    protected ?array $collectionOptionsCache = null;
    protected ?array $designerOptionsCache = null;
    private bool $sectionIdResolved = false;
    private ?int $sectionIdMemo = null;

    protected function brand()
    {
        return $this->getOwnerRecord()->brand;
    }

    /** The brand's enabled wallpapers section id (memoized — runs in several action closures per render). */
    protected function sectionId(): ?int
    {
        if (! $this->sectionIdResolved) {
            $this->sectionIdMemo = $this->brand()?->sectionByKey('wallpapers')?->id;
            $this->sectionIdResolved = true;
        }
        return $this->sectionIdMemo;
    }

    /** Collections scoped to THIS model (per-model sub-sections). Memoized per request. */
    protected function collectionOptions(): array
    {
        if ($this->collectionOptionsCache !== null) {
            return $this->collectionOptionsCache;
        }
        $model = $this->getOwnerRecord();
        return $this->collectionOptionsCache = ContentCollection::where('brand_id', $model->brand_id)
            ->where('car_model_id', $model->id)
            ->where('is_active', true)->orderBy('sort_order')
            ->get()->mapWithKeys(fn($c) => [$c->id => ($c->icon ? $c->icon . ' ' : '') . $c->name_ar])
            ->toArray();
    }

    /** Memoized per request (was re-queried for every table row). */
    protected function designerOptions(): array
    {
        return $this->designerOptionsCache ??= Designer::active()->pluck('name_ar', 'id')->toArray();
    }

    /** Other models (across all brands) to move/copy wallpapers into. */
    protected function otherModelOptions(): array
    {
        return CarModel::with('brand')
            ->where('is_active', true)
            ->where('id', '!=', $this->getOwnerRecord()->id)
            ->get()
            ->sortBy(fn ($m) => ($m->brand?->name_ar ?? '') . ' ' . $m->name_ar)
            ->mapWithKeys(fn ($m) => [
                $m->id => ($m->brand?->name_ar ? $m->brand->name_ar . ' — ' : '') . $m->name_ar,
            ])
            ->toArray();
    }

    /** Resolve the target model + its enabled wallpapers section, or notify. */
    protected function resolveTargetModel(int $modelId): ?array
    {
        $target = CarModel::with('brand')->find($modelId);
        $sectionId = $target?->brand?->sectionByKey('wallpapers')?->id;

        if (! $target || ! $sectionId) {
            Notification::make()
                ->title('فعّل قسم "الخلفيات" لماركة الموديل الهدف أولاً')
                ->danger()->send();
            return null;
        }

        return ['model' => $target, 'section_id' => $sectionId];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title_ar')->label('العنوان (اختياري)')->maxLength(255),
            Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                ->options(fn() => $this->collectionOptions())->searchable()->nullable()
                ->placeholder('بدون قسم فرعي')
                ->helperText('أنشئ الأقسام الفرعية من تبويب "الأقسام الفرعية"'),
            Forms\Components\Select::make('designer_id')->label('المصمّم')
                ->options(fn() => $this->designerOptions())->searchable()->nullable()
                ->placeholder('بدون مصمّم')
                ->helperText('أضف المصممين من قسم "المصمّمون"'),
            Forms\Components\FileUpload::make('image_path')->label('الصورة')
                ->image()->directory('content-items/images')->required()->columnSpanFull(),
            ...$this->watermarkFields(),
            Forms\Components\Select::make('status')->label('الحالة')
                ->options(['published' => 'منشور', 'draft' => 'مسودة'])->default('published'),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Toggle::make('is_paid')->label('💰 خلفية مدفوعة')->inline(false)->live()
                    ->helperText('فعّلها لبيع الخلفية بسعر'),
                Forms\Components\TextInput::make('price')->label('السعر')->numeric()
                    ->visible(fn (Forms\Get $get) => $get('is_paid'))
                    ->required(fn (Forms\Get $get) => $get('is_paid')),
                Forms\Components\Select::make('currency')->label('العملة')
                    ->options(['QAR' => 'ريال قطري', 'SAR' => 'ريال سعودي', 'AED' => 'درهم', 'KWD' => 'دينار كويتي', 'USD' => 'دولار'])
                    ->default('QAR')
                    ->visible(fn (Forms\Get $get) => $get('is_paid')),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_ar')
            ->defaultSort('created_at', 'desc')
            ->deferLoading() // render the model page first, then load this image-heavy table
            ->modifyQueryUsing(fn ($query) => $query->with('designer:id,name_ar'))
            ->paginationPageOptions([12, 24, 48, 100])
            ->defaultPaginationPageOption(12)
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_path')->label('')
                    ->disk(config('filesystems.default', 'public'))->square()->size(100)
                    ->extraImgAttributes(fn ($record) => [
                        'style' => 'cursor: zoom-in;',
                        'loading' => 'lazy',
                        'data-zoom-src' => $record->image_url,
                    ]),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->placeholder('—')->limit(30),
                Tables\Columns\SelectColumn::make('content_collection_id')->label('القسم الفرعي')
                    ->options(fn() => $this->collectionOptions())
                    ->placeholder('بدون قسم فرعي')->width('160px'),
                // Display-only (was an inline select that embedded every designer's options into
                // each row — heavy to re-render). Assign via the "تعيين مصمّم" bulk action or Edit.
                Tables\Columns\TextColumn::make('designer.name_ar')->label('المصمّم')
                    ->placeholder('بدون مصمّم')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['success' => 'published', 'gray' => 'draft']),
                Tables\Columns\IconColumn::make('watermark_id')->label('موقّعة')
                    ->trueIcon('heroicon-s-finger-print')->falseIcon('heroicon-o-minus')
                    ->trueColor('info')->falseColor('gray')
                    ->state(fn ($record) => (bool) $record->watermark_id),
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
                        ...$this->watermarkFields(),
                    ])
                    ->action(function (array $data) {
                        $sectionId = $this->sectionId();
                        if (!$sectionId) {
                            Notification::make()->title('فعّل قسم "الخلفيات" للماركة أولاً')->danger()->send();
                            return;
                        }
                        $model = $this->getOwnerRecord();
                        $watermarkId = $data['watermark_id'] ?? null;
                        $position = $data['watermark_position'] ?? null;
                        $count = 0;
                        foreach (($data['images'] ?? []) as $path) {
                            $item = ContentItem::create([
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
                            $this->finalizeUpload($item, $watermarkId ? (int) $watermarkId : null, $position);
                            $count++;
                        }
                        $suffix = $watermarkId ? ' مع التوقيع' : '';
                        Notification::make()->title("تم رفع {$count} خلفية لهذا الموديل{$suffix}")->success()->send();
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
                    })
                    ->after(fn (ContentItem $record) => $this->finalizeUpload($record, $record->watermark_id)),
            ])
            ->actions([
                $this->publishToTelegramAction(),
                $this->applyWatermarkRowAction(),
                Tables\Actions\EditAction::make()
                    ->after(fn (ContentItem $record) => $this->syncWatermarkAfterSave($record)),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    $this->publishToTelegramBulkAction(),
                    Tables\Actions\BulkAction::make('assignCollection')->label('تعيين لقسم فرعي')
                        ->icon('heroicon-o-folder-arrow-down')->color('warning')
                        ->modalHeading('تعيين الخلفيات المحددة لقسم فرعي')
                        ->form([
                            Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                                ->options(fn() => $this->collectionOptions())->nullable()->placeholder('بدون قسم فرعي'),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['content_collection_id' => $data['content_collection_id'] ?? null]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('assignDesigner')->label('تعيين مصمّم')
                        ->icon('heroicon-o-paint-brush')->color('warning')
                        ->modalHeading('تعيين مصمّم للخلفيات المحددة')
                        ->form([
                            Forms\Components\Select::make('designer_id')->label('المصمّم')
                                ->options(fn() => $this->designerOptions())->searchable()->nullable()->placeholder('بدون مصمّم'),
                        ])
                        ->action(fn($records, array $data) => $records->each->update(['designer_id' => $data['designer_id'] ?? null]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('moveToModel')->label('نقل لموديل آخر')
                        ->icon('heroicon-o-arrows-right-left')->color('warning')
                        ->modalHeading('نقل الخلفيات المحددة لموديل آخر')
                        ->modalDescription('تنتقل الخلفيات للموديل الجديد (يُزال القسم الفرعي القديم لأنه خاص بالموديل الأصلي).')
                        ->form([
                            Forms\Components\Select::make('car_model_id')->label('الموديل الهدف')
                                ->options(fn() => $this->otherModelOptions())->required()->searchable(),
                        ])
                        ->action(function ($records, array $data) {
                            if (! $t = $this->resolveTargetModel((int) $data['car_model_id'])) return;
                            foreach ($records as $r) {
                                $r->update([
                                    'car_model_id'          => $t['model']->id,
                                    'brand_id'              => $t['model']->brand_id,
                                    'brand_section_id'      => $t['section_id'],
                                    'content_collection_id' => null,
                                ]);
                            }
                            Notification::make()->title("تم نقل {$records->count()} خلفية إلى {$t['model']->name_ar}")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('copyToModel')->label('نسخ لموديل آخر')
                        ->icon('heroicon-o-document-duplicate')->color('info')
                        ->modalHeading('نسخ الخلفيات المحددة لموديل آخر')
                        ->modalDescription('تبقى الخلفيات في الموديل الحالي، وتُنشأ نسخة منها في الموديل الهدف.')
                        ->form([
                            Forms\Components\Select::make('car_model_id')->label('الموديل الهدف')
                                ->options(fn() => $this->otherModelOptions())->required()->searchable(),
                        ])
                        ->action(function ($records, array $data) {
                            if (! $t = $this->resolveTargetModel((int) $data['car_model_id'])) return;
                            foreach ($records as $r) {
                                $copy = $r->replicate(['slug']);
                                $copy->car_model_id          = $t['model']->id;
                                $copy->brand_id              = $t['model']->brand_id;
                                $copy->brand_section_id      = $t['section_id'];
                                $copy->content_collection_id = null;
                                $copy->slug                  = null;
                                $copy->save();
                            }
                            Notification::make()->title("تم نسخ {$records->count()} خلفية إلى {$t['model']->name_ar}")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    $this->applyWatermarkBulkAction(),
                    $this->removeWatermarkBulkAction(),
                    Tables\Actions\DeleteBulkAction::make()->label('حذف المحدد')
                        ->visible(fn () => ! auth()->user()?->hasRole('مبدع')),
                ]),
            ]);
    }
}
