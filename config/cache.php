<?php

return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_table' => 'cache_locks',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'wallpaper_cache'),
];
