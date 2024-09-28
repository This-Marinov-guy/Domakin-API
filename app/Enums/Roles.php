<?php

namespace App\Enums;

enum Roles: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case USER = 'user';
}
