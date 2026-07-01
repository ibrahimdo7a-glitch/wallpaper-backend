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
    // No wildcard pattern: anyone could register a matching *.vercel.app subdomain
    // and (with credentials) read cross-origin responses. Pin to exact origins above.
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
