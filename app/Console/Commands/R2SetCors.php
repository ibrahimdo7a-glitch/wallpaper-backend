<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies a CORS policy to the R2 bucket so the admin (FilePond) can load
 * image previews and the frontend can fetch assets cross-origin.
 * Run once on Railway: php artisan r2:cors
 */
class R2SetCors extends Command
{
    protected $signature = 'r2:cors';
    protected $description = 'Apply CORS policy to the Cloudflare R2 bucket';

    public function handle(): int
    {
        $bucket = config('filesystems.disks.r2.bucket');

        if (!$bucket) {
            $this->error('R2 bucket is not configured (filesystems.disks.r2.bucket is empty).');
            return self::FAILURE;
        }

        try {
            /** @var \Aws\S3\S3Client $client */
            $client = Storage::disk('r2')->getClient();

            $client->putBucketCors([
                'Bucket' => $bucket,
                'CORSConfiguration' => [
                    'CORSRules' => [[
                        'AllowedOrigins' => [
                            'https://api.qev.app',
                            'https://qev.app',
                            'https://www.qev.app',
                        ],
                        'AllowedMethods' => ['GET', 'PUT', 'POST', 'HEAD', 'DELETE'],
                        'AllowedHeaders' => ['*'],
                        'ExposeHeaders'  => ['ETag'],
                        'MaxAgeSeconds'  => 3600,
                    ]],
                ],
            ]);

            $this->info("✅ CORS policy applied to R2 bucket: {$bucket}");
            $this->line('Allowed origins: https://api.qev.app, https://qev.app');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to apply CORS: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
