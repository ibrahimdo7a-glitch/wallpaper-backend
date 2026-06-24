<?php

namespace App\Filament\Resources\CarModelResource\RelationManagers;

use App\Models\ContentCollection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModelFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'fileContent';
    protected static ?string $title = 'الملفات';
    protected static ?string $modelLabel = 'ملف';
    protected static ?string $pluralModelLabel = 'الملفات';
    protected static ?string $icon = 'heroicon-o-document';

    protected function section()
    {
        $brand = $this->getOwnerRecord()->brand;
        return $brand?->sectionByKey('files') ?? $brand?->sectionByKey('manuals');
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
                Forms\Components\TextInput::make('title_ar')->label('اسم الملف')->required()->maxLength(255),
                Forms\Components\Select::make('content_collection_id')->label('القسم الفرعي')
                    ->options(fn() => $this->collectionOptions())->searchable()->nullable()->placeholder('بدون قسم فرعي'),
            ]),
            Forms\Components\Textarea::make('description_ar')->label('الوصف')->rows(2),
            Forms\Components\FileUpload::make('file_path')->label('الملف')
                ->directory('content-items/files')->maxSize(512000)->required(),
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
                Tables\Columns\TextColumn::make('title_ar')->label('الاسم')->searchable()->limit(45)->icon('heroicon-o-document'),
                Tables\Columns\SelectColumn::make('content_collection_id')->label('القسم الفرعي')
                    ->options(fn() => $this->collectionOptions())->placeholder('بدون قسم فرعي')->width('180px'),
                Tables\Columns\TextColumn::make('downloads_count')->label('تحميلات')->sortable(),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')->colors(['success' => 'published', 'gray' => 'draft']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('content_collection_id')->label('القسم الفرعي')->options(fn() => $this->collectionOptions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة ملف')
                    ->disabled(fn() => $this->section() === null)
                    ->tooltip(fn() => $this->section() === null ? 'فعّل قسم الملفات للماركة أولاً' : null)
                    ->mutateFormDataUsing(function (array $data) {
                        $model = $this->getOwnerRecord();
                        $section = $this->section();
                        $data['brand_id']         = $model->brand_id;
                        $data['brand_section_id'] = $section?->id;
                        $data['content_type']     = $section?->sectionType?->key ?? 'files';
                        return $data;
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }
}
