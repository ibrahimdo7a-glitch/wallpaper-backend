<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://qev.app',
        'https://www.qev.app',
        'https://wallpaper-frontend-ten.vercel.app',
        'http://localhost:3000',
    ],
    'allowed_origins_patterns' => [
        '/^https:\/\/wallpaper-frontend-.*\.vercel\.app$/',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
