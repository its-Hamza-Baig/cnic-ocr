<?php

return [

    'pattern' => '/^\d{5}-\d{7}-\d{1}$/',

    'name_max_length' => 255,

    'address_max_length' => 2000,

    'disk' => env('CNIC_DISK', 'cnic'),

    'temp_image_retention_hours' => (int) env('CNIC_TEMP_IMAGE_RETENTION_HOURS', 24),

    /*
    | Leave empty to keep saved person images indefinitely.
    */
    'person_image_retention_days' => env('CNIC_PERSON_IMAGE_RETENTION_DAYS'),

    'sensitive_rate_limit_per_minute' => (int) env('CNIC_SENSITIVE_RATE_LIMIT_PER_MINUTE', 30),

];
