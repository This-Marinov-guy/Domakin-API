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
        'type',
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
            'type' => 'required|string',
            'move_in' => 'required|string',
            'period' => 'required|string',
            'registration' => 'required|string',
            'budget' => 'required|integer',
            'city' => 'required|string',
            'note' => 'nullable|string',

            'terms' => 'required|array', 
            'terms.contact' => 'required|accepted',
            'terms.legals' => 'required|accepted',
        ];
    }

    public static function messages()
    {
        return [];
    }
}
