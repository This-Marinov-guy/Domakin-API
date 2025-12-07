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
        'interface',
    ];

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string|min:6',
            'email' => 'required|string|email',
            'letter' => 'required|file|mimes:pdf,doc,docx|max:4120',
            'interface' => 'required|string|in:web,mobile,signal',

            'terms' => 'required|array',
            'terms.contact' => 'required|accepted',
            'terms.legals' => 'required|accepted',
        ];
    }

    public static function messages()
    {
        return [
            'email.email' => [
                'tag' => 'account:authentication.errors.email_invalid',
            ],
            'phone.min' => [
                'tag' => 'account:authentication.errors.phone_invalid',
            ],
            'terms.contact.accepted' => [
                'tag' => 'account:authentication.errors.terms_must_be_accepted',
            ],
            'terms.legals.accepted' => [
                'tag' => 'account:authentication.errors.terms_must_be_accepted',
            ],
        ];
    }
}
