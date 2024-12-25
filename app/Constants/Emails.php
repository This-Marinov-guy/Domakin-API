<?php

namespace App\Constants;

class Emails
{
    public const MAILTRAP = [
        'email' => env('MAILTRAP_FROM_ADDRESS'),
        'name' => env('MAILTRAP_FROM_NAME')
    ];

    public const SYSTEM = [
        'email' => env('GMAIL_FROM_ADDRESS'),
        'name' => env('GMAIL_FROM_NAME')
    ];
}
