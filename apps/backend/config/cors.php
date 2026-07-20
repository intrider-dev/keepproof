<?php

declare(strict_types=1);

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080,http://localhost:5173')),
    )),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type', 'Origin', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-Correlation-ID'],
    'exposed_headers' => ['X-Correlation-ID'],
    'max_age' => 600,
    'supports_credentials' => true,
];
