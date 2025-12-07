<?php

namespace App\Models;

use App\Models\Concerns\HasDomainBasedTermsValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Renting extends Model
{
    use HasFactory, HasDomainBasedTermsValidation;

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

    public static function rules($request = null): array
    {
        $rules = [
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string|min:6',
            'email' => 'required|string|email',
            'letter' => 'required|file|mimes:pdf,doc,docx|max:4120',
            'interface' => 'required|string|in:web,mobile,signal',
        ];

        // Add terms validation rules based on domain
        $rules = array_merge($rules, static::getTermsValidationRules($request));

        return $rules;
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
