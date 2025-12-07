<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Endpoint Patterns
    |--------------------------------------------------------------------------
    |
    | These patterns define which API endpoints are public and bypass the
    | firewall. These routes also bypass CSRF protection.
    |
    | Patterns support wildcards (*) for flexible matching.
    |
    */

    'public_patterns' => [
        'api/webhooks/stripe/*',
        'api/blog/*',
        'api/property/*',
        'api/feedback/list',
        'api/renting/create', // Excluded from firewall, uses DomainWhitelistMiddleware instead
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should bypass CSRF token verification.
    | These typically match the public_patterns above.
    |
    */

    'csrf_excluded' => [
        'api/webhooks/stripe/*',
        // 'api/property/*',
        // 'api/feedback/list',
        'api/renting/create',
    ],
];

