<?php

return [
    // Default API rate limit per IP per minute; overridable via env
    'api_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),

    // Default login rate limit per email+IP per minute; overridable via env
    'login_per_minute' => env('LOGIN_RATE_LIMIT_PER_MINUTE', 10),
];


