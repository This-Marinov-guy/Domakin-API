<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Axiom API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Axiom API integration for logging API requests/responses.
    |
    */

    // Axiom API token
    'api_token' => env('AXIOM_TOKEN'),

    // Axiom dataset name to send logs to
    'dataset' => env('AXIOM_DATASET', 'api-logs'),

    // Enable/disable logging (useful for switching off in certain environments)
    'enabled' => env('AXIOM_LOGGING_ENABLED', true),

    // Maximum size in bytes of request/response bodies to log
    // Default is 1MB to prevent excessive logging
    'max_content_size' => env('AXIOM_MAX_CONTENT_SIZE', 1000000),

    // Additional fields to include in every log
    'additional_fields' => [
        // Add any global fields you want in every log entry
        // Example: 'app_version' => '1.0.0',
    ],
];
