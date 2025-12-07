<?php

namespace App\Models;

use App\Models\Concerns\HasDomainBasedTermsValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viewing extends Model
{
    use HasFactory, HasDomainBasedTermsValidation;

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
        'referral_code',
        'google_calendar_id',
        'payment_link',
        'interface',
    ];

    protected $attributes = [
        'status' => 1
    ];

    public static function rules($request = null): array
    {
        $rules = [
            'name' => 'required|string',
            'surname' => 'required|string',
            'phone' => 'required|string|min:6',
            'email' => 'required|string|email',
            'city' => 'required|string',
            'address' => 'required|string|max:50',
            'date' => 'required|string',
            'time' => 'required|string',
            'interface' => 'required|string|in:web,mobile,signal',
        ];

        // Add terms validation rules based on domain
        $rules = array_merge($rules, static::getTermsValidationRules($request));

        return $rules;
    }

    // TODO: add messages
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
