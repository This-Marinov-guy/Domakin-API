<?php

namespace App\Constants;

class ErrorMessages
{
    public const GENERAL = [
        'message' => 'Something went wrong, please try again!',
        'tag' => 'api.general_error'
    ];

    public const REQUIRED_FIELDS = [
        'message' => 'Please fill the required fields!',
        'tag' => 'api.fill_fields'
    ];
}
