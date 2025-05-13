<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    protected $table = 'newsletters';

    protected $fillable = [
        'cities',
        'email',
    ];

    public static function rules(): array
    {
        return [
            'cities' => 'required|string',
            'email' => 'required|string|unique:newsletters,email|email',
        ];
    }

    public static function messages()
    {
        return [
            'email.email' => [
                'tag' => 'account:authentication.errors.email',
            ],
            'email.unique' => [
                'tag' => 'account:authentication.errors.email',
            ],
        ];
    }
}
