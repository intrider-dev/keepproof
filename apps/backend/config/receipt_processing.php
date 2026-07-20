<?php

declare(strict_types=1);

return [
    'disk' => env('RECEIPT_DISK', env('FILESYSTEM_DISK', 'local')),
    'ocr_driver' => env('OCR_DRIVER', 'fixture'),
    'data_extractor' => env('RECEIPT_DATA_EXTRACTOR', 'patterns'),
    'signed_url_minutes' => (int) env('RECEIPT_SIGNED_URL_MINUTES', 5),
    'reconcile_after_seconds' => (int) env('RECEIPT_RECONCILE_AFTER_SECONDS', 300),
    'reconcile_batch_size' => (int) env('RECEIPT_RECONCILE_BATCH_SIZE', 100),
    'external_ai_enabled' => (bool) env('EXTERNAL_AI_ENABLED', false),
];
