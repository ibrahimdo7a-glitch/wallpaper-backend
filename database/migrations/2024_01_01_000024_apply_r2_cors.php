<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Applies a CORS policy to the Cloudflare R2 bucket automatically on deploy,
 * so the Filament admin (FilePond) can fetch existing image previews without
 * hanging on "Waiting for size". Wrapped in try/catch so a CORS failure never
 * blocks the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('filesystems.default') !== 'r2') {
            return;
        }

        $bucket = config('filesystems.disks.r2.bucket');
        if (!$bucket) {
            return;
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

            \Log::info("R2 CORS policy applied to bucket: {$bucket}");
        } catch (\Throwable $e) {
            // Never block the deploy if CORS can't be set.
            \Log::warning('R2 CORS migration could not apply policy: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
