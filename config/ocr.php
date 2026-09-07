<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Provider
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "fake", "paddleocr", "tesseract", "google_vision".
    | The fake driver is for local development and automated tests.
    |
    */

    'provider' => env('OCR_PROVIDER', 'paddleocr'),

    'timeout_seconds' => (int) env('OCR_TIMEOUT_SECONDS', 180),

    'rate_limit_per_minute' => (int) env('OCR_RATE_LIMIT_PER_MINUTE', 10),

    'max_upload_kb' => (int) env('OCR_MAX_UPLOAD_KB', 5120),

    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
    ],

    'allowed_extensions' => ['jpg', 'jpeg', 'png'],

    /*
    |--------------------------------------------------------------------------
    | Raw OCR text (debug only)
    |--------------------------------------------------------------------------
    |
    | Raw OCR output is not stored by default. Enable only for short-lived
    | debugging. Values are encrypted at rest and pruned automatically.
    |
    */

    'store_raw_text' => (bool) env('OCR_STORE_RAW_TEXT', false),

    'raw_text_retention_hours' => (int) env('OCR_RAW_TEXT_RETENTION_HOURS', 24),

    'tesseract' => [
        'binary' => env('TESSERACT_BINARY', 'tesseract'),
        'language' => env('TESSERACT_LANG', 'eng'),
        'back_language' => env('TESSERACT_BACK_LANG', 'urd'),
        'scripts' => env('TESSERACT_SCRIPTS', 'eng,urd'),
        'psm' => env('TESSERACT_PSM', '6'),
        'docker_image' => env('TESSERACT_DOCKER_IMAGE', 'cnic-ocr-tesseract'),
    ],

    'paddleocr' => [
        'url' => env('PADDLEOCR_URL', 'http://127.0.0.1:8868'),
        'langs' => env('PADDLEOCR_LANGS', 'en,ar'),
    ],

    'google_vision' => [
        'api_key' => env('GOOGLE_VISION_API_KEY'),
        'endpoint' => env('GOOGLE_VISION_ENDPOINT', 'https://vision.googleapis.com/v1/images:annotate'),
    ],

];
