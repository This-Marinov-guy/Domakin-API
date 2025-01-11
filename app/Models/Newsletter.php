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
            'cities' => 'required|array',
            'cities.*' => 'string',
            
            'email' => 'required|string|email',
        ];
    }

    public static function messages()
    {
        return [];
    }
}
