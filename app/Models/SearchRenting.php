<?php

namespace App\Models;

use App\Models\Concerns\HasDomainBasedTermsValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchRenting extends Model
{
    use HasFactory, HasDomainBasedTermsValidation;

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
            'letter' => 'nullable|file|mimes:pdf,doc,docx|max:4120',
            'people' => 'required|integer',
            'type' => 'required|string',
            'move_in' => 'required|string',
            'period' => 'required|string',
            'registration' => 'required|string',
            'budget' => 'required|integer',
            'city' => 'required|string',
            'note' => 'nullable|string',
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
