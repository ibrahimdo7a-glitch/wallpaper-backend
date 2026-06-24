<?php

namespace App\Filament\Resources\CarModelResource\RelationManagers;

use App\Models\ContentCollection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModelTutorialsRelationManager extends RelationManager
{
    use \App\Filament\Concerns\RelationHiddenFromCreatives;
    protected static string $relationship = 'tutorialContent';
    protected static ?string $title = 'الشروحات';
    protected static ?string $modelLabel = 'شرح';
    protected static ?string $pluralModelLabel = 'الشروحات';
    protected static ?string $icon = 'heroicon-o-academic-cap';

    protected function section()
    {
        $brand = $this->getOwnerRecord()->brand;
        return $brand?->sectionByKey('tutorials')
            ?? $brand?->sectionByKey('tutorial_images')
            ?? $brand?->sectionByKey('tutorial_videos');
    }

    protected function collectionOptions(): array
    {
        $model = $this->getOwnerRecord();
        return ContentCollection::where('brand_id', $model->brand_id)
            ->where('car_model_id', $model->id)
            ->where('is_active', true)->orderBy('sort_order')
            ->get()->mapWithKeys(fn($c) => [$c->id => ($c->icon ? $c->icon . ' ' : '') . $c->name_ar])->toArray();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('title_ar')->label('عنوان الشرح')->required()->maxLength(255),
                Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                    ->options(fn() => $this->collectionOptions())->searchable()->nullable()->placeholder('بدون قسم فرعي'),
            ]),
            Forms\Components\Textarea::make('description_ar')->label('الوصف')->rows(2),
            Forms\Components\FileUpload::make('image_path')->label('صورة الشرح / الغلاف')
                ->image()->directory('content-items/tutorials'),
            Forms\Components\TextInput::make('video_url')->label('رابط الفيديو (YouTube) — اختياري')->url(),
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
                Tables\Columns\ImageColumn::make('image_path')->label('')->disk(config('filesystems.default', 'public'))->square()->size(48),
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->searchable()->limit(40),
                Tables\Columns\IconColumn::make('video_url')->label('فيديو')->boolean()
                    ->trueIcon('heroicon-o-play-circle')->falseIcon('heroicon-o-photo'),
                Tables\Columns\SelectColumn::make('content_collection_id')->label('القسم الفرعي')
                    ->options(fn() => $this->collectionOptions())->placeholder('بدون قسم فرعي')->width('180px'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['success' => 'published', 'gray' => 'draft']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('content_collection_id')->label('القسم الفرعي')->options(fn() => $this->collectionOptions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة شرح')
                    ->disabled(fn() => $this->section() === null)
                    ->tooltip(fn() => $this->section() === null ? 'فعّل قسم الشروحات للماركة أولاً' : null)
                    ->mutateFormDataUsing(function (array $data) {
                        $model = $this->getOwnerRecord();
                        $section = $this->section();
                        $data['brand_id']         = $model->brand_id;
                        $data['brand_section_id'] = $section?->id;
                        $data['content_type']     = $section?->sectionType?->key ?? 'tutorials';
                        return $data;
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
