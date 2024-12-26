<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viewing extends Model
{
    use HasFactory;

    protected $table = 'viewings';

    protected $fillable = [
        'name',
        'surname',
        'phone',
        'email',
        'city',
        'address',
        'date',
        'time',
        'note',
    ];

    protected $attributes = [
        'status' => 1
    ];

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|string|email',
            'city' => 'required|string',
            'address' => 'required|string|max:50',
            'date' => 'required|string',
            'time' => 'required|string',

            'terms.contact' => 'required|boolean',
            'terms.legals' => 'required|boolean',
        ];
    }

    // TODO: add messages
    public static function messages()
    {
        return [
            
        ];
    }
    
}
