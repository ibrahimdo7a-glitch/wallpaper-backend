<?php

namespace App\Console\Commands;

use App\Models\ContentItem;
use App\Services\ImageThumbnailService;
use Illuminate\Console\Command;

class GenerateContentThumbnails extends Command
{
    protected $signature = 'content:thumbnails {--force : Regenerate even if a generated thumbnail already exists}';

    protected $description = 'Generate small WebP thumbnails for existing content images (speeds up admin tables & grids).';

    public function handle(ImageThumbnailService $thumbs): int
    {
        $total = ContentItem::whereNotNull('image_path')->count();
        $this->info("Processing {$total} content items...");

        $done = 0;
        $skipped = 0;
        $failed = 0;

        ContentItem::whereNotNull('image_path')->orderBy('id')
            ->chunkById(50, function ($items) use ($thumbs, &$done, &$skipped, &$failed) {
                foreach ($items as $item) {
                    if (! $this->option('force')
                        && $item->thumbnail_path
                        && str_contains($item->thumbnail_path, 'content-items/thumbs')) {
                        $skipped++;
                        continue;
                    }

                    $thumbs->refreshFor($item) ? $done++ : $failed++;
                    $this->output->write('.');
                }
            });

        $this->newLine();
        $this->info("Done. Generated: {$done}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
