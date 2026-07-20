<?php

declare(strict_types=1);

return [
    'disk' => env('RECEIPT_DISK', env('FILESYSTEM_DISK', 'local')),
    'ocr_driver' => env('OCR_DRIVER', 'fixture'),
    'data_extractor' => env('RECEIPT_DATA_EXTRACTOR', 'patterns'),
    'signed_url_minutes' => (int) env('RECEIPT_SIGNED_URL_MINUTES', 5),
    'external_ai_enabled' => (bool) env('EXTERNAL_AI_ENABLED', false),
];
