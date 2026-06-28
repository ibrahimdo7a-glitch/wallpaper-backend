<?php

namespace App\Filament\Concerns;

use App\Filament\Forms\Components\WatermarkPositionPicker;
use App\Models\ContentItem;
use App\Models\Watermark;
use App\Services\ContentWatermarkService;
use App\Services\ImageThumbnailService;
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

    private ?bool $hasWatermarksCache = null;
    private ?bool $tgConfiguredCache = null;

    protected function hasWatermarks(): bool
    {
        // Memoized: this runs in every row action's visible() closure, once per row per render.
        return $this->hasWatermarksCache ??= Watermark::where('is_active', true)->exists();
    }

    /** Memoized Telegram-configured check (also evaluated per row per render). */
    protected function telegramConfigured(): bool
    {
        return $this->tgConfiguredCache ??= app(\App\Services\TelegramService::class)->isConfigured();
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

    /**
     * Finish an upload: burn the watermark if one was chosen (that also rebuilds
     * the thumbnail), otherwise just build a lightweight thumbnail so admin tables
     * and grids don't load the full-size image.
     */
    protected function finalizeUpload(ContentItem $item, ?int $watermarkId, ?string $position = null): void
    {
        if (! $this->applyWatermark($item, $watermarkId, $position)) {
            app(ImageThumbnailService::class)->refreshFor($item);
        }
    }

    /** Per-row action to post a wallpaper to the configured Telegram channel. */
    protected function publishToTelegramAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('publishTelegram')
            ->label('نشر في تلجرام')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn () => $this->telegramConfigured())
            ->modalHeading('نشر الخلفية في قناة تلجرام')
            ->modalSubmitActionLabel('نشر')
            ->form([
                Forms\Components\Textarea::make('caption')
                    ->label('نص فوق البوست (اختياري)')
                    ->rows(2)
                    ->helperText('يُضاف تلقائيًا تحته: اسم المصمّم + رابط صفحة الخلفية + رابط الموقع.')
                    ->default(fn (ContentItem $record) => $record->title_ar ?: ''),
            ])
            ->action(function (ContentItem $record, array $data) {
                $url = $record->image_url;
                if (! $url) {
                    Notification::make()->title('لا توجد صورة لنشرها')->danger()->send();
                    return;
                }
                $res = app(\App\Services\TelegramService::class)->sendPhoto($url, $record->telegramCaption($data['caption'] ?? null));
                $res['ok']
                    ? Notification::make()->title('تم النشر في القناة ✓')->success()->send()
                    : Notification::make()->title('فشل النشر في تلجرام')->body($res['error'] ?? '')->danger()->send();
            });
    }

    /**
     * Bulk action: post each selected wallpaper as a SEPARATE Telegram post,
     * 5 seconds apart. Runs synchronously (no queue worker on this host), so the
     * batch is capped to keep the request from timing out / freezing the panel.
     */
    protected function publishToTelegramBulkAction(): Tables\Actions\BulkAction
    {
        $limit = 20;

        return Tables\Actions\BulkAction::make('publishTelegramBulk')
            ->label('نشر في تلجرام')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn () => $this->telegramConfigured())
            ->requiresConfirmation()
            ->modalHeading('نشر الخلفيات المحددة في القناة')
            ->modalDescription("كل خلفية تُنشر كبوست منفصل بفاصل ٥ ثوانٍ. لا تغلق الصفحة حتى تنتهي. (بحد أقصى {$limit} في المرة — للمزيد كرّر العملية)")
            ->modalSubmitActionLabel('نشر')
            ->action(function ($records) use ($limit) {
                @set_time_limit(0);
                $tg = app(\App\Services\TelegramService::class);

                $batch = $records->take($limit);
                $sent = 0;
                $failed = 0;
                $first = true;

                foreach ($batch as $record) {
                    if (! $record->image_url) {
                        $failed++;
                        continue;
                    }
                    if (! $first) {
                        sleep(5);
                    }
                    $first = false;

                    $res = $tg->sendPhoto($record->image_url, $record->telegramCaption());
                    $res['ok'] ? $sent++ : $failed++;
                }

                $extra = $records->count() > $limit ? ' — تبقّى ' . ($records->count() - $limit) . ' حدّدها وكرّر' : '';
                Notification::make()
                    ->title("نُشرت {$sent} خلفية" . ($failed ? " — فشل {$failed}" : '') . $extra)
                    ->{$failed ? 'warning' : 'success'}()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
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
        } elseif (! $record->watermark_id && $record->wasChanged('image_path')) {
            // Image replaced on a non-watermarked item → refresh its thumbnail.
            app(ImageThumbnailService::class)->refreshFor($record);
        }
    }
}
