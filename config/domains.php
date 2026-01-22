<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Domains for Renting Endpoint
    |--------------------------------------------------------------------------
    |
    | List of domains that are allowed to access the renting/create endpoint.
    | Domains can be specified with or without protocol.
    | Subdomains are automatically allowed (e.g., 'domakin.nl' allows 'www.domakin.nl')
    |
    */

    'allowed_domains' => [
        'domakin.nl',
        'demo.domakin.nl',
        'www.domakin.nl',
        // Add more domains as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Environment Domains
    |--------------------------------------------------------------------------
    |
    | Additional domains allowed in development environment
    |
    */

    'dev_domains' => [
        'localhost',
        '127.0.0.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domains That Require Terms Acceptance
    |--------------------------------------------------------------------------
    |
    | List of domains that require terms.contact and terms.legals to be accepted.
    | If a domain is not in this list, terms validation will be optional.
    |
    */

    'terms_required_domains' => [
        'localhost',
        'http://localhost:3000',
        '127.0.0.1',
        'domakin.nl',
        'www.domakin.nl',
        'demo.domakin.nl',
        'https://www.domakin.nl',
        'https://www.demo.domakin.nl',
        'https://domakin.nl',
        'https://demo.domakin.nl',
        // Add domains that require terms acceptance
    ],
];

