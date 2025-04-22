<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Renting extends Model
{
    use HasFactory;

    protected $table = 'rentings';

    protected $fillable = [
        'property',
        'name',
        'surname',
        'phone',
        'email',
        'letter',
        'note',
        'referral_code',
    ];

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|string|email',
            'letter' => 'required|file|mimes:pdf,doc,docx|max:4120',

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
