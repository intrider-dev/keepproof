<?php

declare(strict_types=1);

return [
    'disk' => env('RECEIPT_DISK', env('FILESYSTEM_DISK', 'local')),
    'ocr_driver' => env('OCR_DRIVER', 'fixture'),
    'data_extractor' => env('RECEIPT_DATA_EXTRACTOR', 'patterns'),
    'signed_url_minutes' => (int) env('RECEIPT_SIGNED_URL_MINUTES', 5),
    'reconcile_after_seconds' => (int) env('RECEIPT_RECONCILE_AFTER_SECONDS', 300),
    'reconcile_batch_size' => (int) env('RECEIPT_RECONCILE_BATCH_SIZE', 100),
    'external_ai' => [
        'enabled' => (bool) env('EXTERNAL_AI_ENABLED', false),
        'endpoint' => env('EXTERNAL_AI_ENDPOINT'),
        'api_key' => env('EXTERNAL_AI_API_KEY'),
        'provider' => env('EXTERNAL_AI_PROVIDER', 'generic-json'),
        'model' => env('EXTERNAL_AI_MODEL', 'configured-model'),
        'connect_timeout_seconds' => (int) env('EXTERNAL_AI_CONNECT_TIMEOUT_SECONDS', 3),
        'timeout_seconds' => (int) env('EXTERNAL_AI_TIMEOUT_SECONDS', 10),
        'http_attempts' => (int) env('EXTERNAL_AI_HTTP_ATTEMPTS', 2),
        'retry_delay_ms' => (int) env('EXTERNAL_AI_RETRY_DELAY_MS', 200),
        'max_input_chars' => (int) env('EXTERNAL_AI_MAX_INPUT_CHARS', 16000),
        'max_input_tokens' => (int) env('EXTERNAL_AI_MAX_INPUT_TOKENS', 8000),
        'max_output_tokens' => (int) env('EXTERNAL_AI_MAX_OUTPUT_TOKENS', 1200),
        'max_estimated_cost_micros' => (int) env('EXTERNAL_AI_MAX_ESTIMATED_COST_MICROS', 25000),
        'max_response_bytes' => (int) env('EXTERNAL_AI_MAX_RESPONSE_BYTES', 65536),
    ],
];
