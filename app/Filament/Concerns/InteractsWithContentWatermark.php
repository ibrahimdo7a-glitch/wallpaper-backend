<?php

namespace App\Filament\Concerns;

use App\Filament\Forms\Components\WatermarkPositionPicker;
use App\Models\ContentItem;
use App\Models\Watermark;
use App\Services\ContentWatermarkService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Drop-in watermark controls for any wallpaper relation manager:
 * a signature picker + a visual position picker for the create/upload forms,
 * plus row & bulk actions to apply, change or remove a watermark on items that
 * were already uploaded.
 */
trait InteractsWithContentWatermark
{
    protected function watermarkOptions(): array
    {
        return Watermark::where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')
            ->pluck('name', 'id')->toArray();
    }

    protected function hasWatermarks(): bool
    {
        return Watermark::where('is_active', true)->exists();
    }

    /** Signature dropdown. */
    protected function watermarkSelectField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('watermark_id')
            ->label('التوقيع')
            ->options(fn () => $this->watermarkOptions())
            ->searchable()
            ->nullable()
            ->live()
            ->placeholder('بدون توقيع')
            ->helperText('يُحفر على الصورة عند الحفظ. (يُحفظ الأصل بدون توقيع)');
    }

    /** Visual position picker; pass an image URL to show it behind the grid. */
    protected function watermarkPositionField(string|\Closure|null $imageUrl = null): WatermarkPositionPicker
    {
        return WatermarkPositionPicker::make('watermark_position')
            ->label('موضع التوقيع')
            ->image($imageUrl)
            ->visible(fn (Forms\Get $get) => filled($get('watermark_id')));
    }

    /** Signature + position fields for create / bulk-upload forms. */
    protected function watermarkFields(string|\Closure|null $imageUrl = null): array
    {
        return [
            $this->watermarkSelectField(),
            $this->watermarkPositionField($imageUrl),
        ];
    }

    protected function applyWatermark(ContentItem $item, ?int $watermarkId, ?string $position = null): bool
    {
        if (! $watermarkId) {
            return false;
        }
        $watermark = Watermark::find($watermarkId);
        if (! $watermark) {
            return false;
        }
        return app(ContentWatermarkService::class)->apply($item, $watermark, $position);
    }

    /** Per-row action to apply or change a watermark on an existing image. */
    protected function applyWatermarkRowAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('applyWatermark')
            ->label('توقيع')
            ->icon('heroicon-o-finger-print')
            ->color('info')
            ->visible(fn () => $this->hasWatermarks())
            ->modalHeading('تطبيق توقيع على الصورة')
            ->modalSubmitActionLabel('تطبيق')
            ->fillForm(fn (ContentItem $record) => [
                'watermark_id'       => $record->watermark_id,
                'watermark_position' => $record->watermark_position ?: 'bottom-left',
            ])
            ->form([
                Forms\Components\Select::make('watermark_id')->label('اختر التوقيع')
                    ->options(fn () => $this->watermarkOptions())->required()->live(),
                $this->watermarkPositionField(fn (ContentItem $record) => $record->image_url),
            ])
            ->action(function (ContentItem $record, array $data) {
                $ok = $this->applyWatermark($record, (int) $data['watermark_id'], $data['watermark_position'] ?? null);
                $ok
                    ? Notification::make()->title('تم تطبيق التوقيع')->success()->send()
                    : Notification::make()->title('تعذّر تطبيق التوقيع على هذه الصورة')->danger()->send();
            });
    }

    /** Bulk action to apply / change a watermark on many images at once. */
    protected function applyWatermarkBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('applyWatermarkBulk')
            ->label('تطبيق توقيع')
            ->icon('heroicon-o-finger-print')
            ->color('info')
            ->visible(fn () => $this->hasWatermarks())
            ->modalHeading('تطبيق توقيع على الصور المحددة')
            ->form([
                Forms\Components\Select::make('watermark_id')->label('اختر التوقيع')
                    ->options(fn () => $this->watermarkOptions())->required()->live(),
                $this->watermarkPositionField(),
            ])
            ->action(function ($records, array $data) {
                $wid = (int) $data['watermark_id'];
                $pos = $data['watermark_position'] ?? null;
                $count = 0;
                foreach ($records as $record) {
                    if ($this->applyWatermark($record, $wid, $pos)) {
                        $count++;
                    }
                }
                Notification::make()->title("تم تطبيق التوقيع على {$count} صورة")->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** Bulk action to strip the watermark and restore the clean originals. */
    protected function removeWatermarkBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('removeWatermarkBulk')
            ->label('إزالة التوقيع')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('سترجع الصور المحددة للأصل بدون توقيع.')
            ->action(function ($records) {
                $svc = app(ContentWatermarkService::class);
                $count = 0;
                foreach ($records as $record) {
                    if ($svc->remove($record)) {
                        $count++;
                    }
                }
                Notification::make()->title("تمت إزالة التوقيع من {$count} صورة")->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** after() hook body shared by create/edit: (re)apply or remove as needed. */
    protected function syncWatermarkAfterSave(ContentItem $record): void
    {
        if ($record->wasChanged('watermark_id')) {
            $record->watermark_id
                ? $this->applyWatermark($record, $record->watermark_id)
                : app(ContentWatermarkService::class)->remove($record);
        } elseif ($record->watermark_id
            && ($record->wasChanged('watermark_position')
                || ($record->wasChanged('image_path')
                    && ! str_contains((string) $record->image_path, 'content-items/watermarked')))) {
            $this->applyWatermark($record, $record->watermark_id);
        }
    }
}
