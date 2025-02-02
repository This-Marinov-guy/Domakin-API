<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchRenting extends Model
{
    use HasFactory;

    protected $table = 'search_rentings';

    protected $fillable = [
        'name',
        'surname',
        'phone',
        'email',
        'letter',
        'people',
        'move_in',
        'period',
        'registration',
        'budget',
        'city',
        'note',
    ];

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|string|email',
            'letter' => 'nullable|file|mimes:pdf,doc,docx|max:4120',
            'people' => 'required|integer',
            'move_in' => 'required|string',
            'period' => 'required|string',
            'registration' => 'required|string',
            'budget' => 'required|integer',
            'city' => 'required|string',
            'note' => 'nullable|string',

            'terms.contact' => 'required|boolean',
            'terms.legals' => 'required|boolean',
        ];
    }

    public static function messages()
    {
        return [];
    }
}
