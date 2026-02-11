<?php

namespace App\Models;

use App\Models\Concerns\HasDomainBasedTermsValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListingApplication extends Model
{
    use HasFactory;
    use HasDomainBasedTermsValidation;

    protected $table = 'listing_applications';

    protected $fillable = [
        'reference_id',
        'user_id',
        'step',
        'name',
        'surname',
        'email',
        'phone',
        'city',
        'address',
        'postcode',
        'size',
        'rent',
        'registration',
        'bills',
        'flatmates',
        'period',
        'description',
        'images',
        'pets_allowed',
        'smoking_allowed',
        'available_from',
        'available_to',
        'type',
        'furnished_type',
        'shared_space',
        'bathrooms',
        'toilets',
        'amenities',
    ];

    protected $casts = [
        'bills'          => 'array',
        'flatmates'      => 'array',
        'period'         => 'array',
        'description'    => 'array',
        'registration'   => 'boolean',
        'pets_allowed'   => 'boolean',
        'smoking_allowed' => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Validation rules per step
    // ---------------------------------------------------------------

    public static function step1Rules(): array
    {
        return [];
    }

    /**
     * Step 2 rules (personal details). Terms are required only for domains in config('domains.terms_required_domains').
     *
     * @param \Illuminate\Http\Request|null $request
     * @return array
     */
    public static function step2Rules($request = null): array
    {
        $rules = [
            'name'    => 'required|string',
            'surname' => 'required|string',
            'email'   => 'required|email',
            'phone'   => 'required|string|min:6',
        ];
        $rules = array_merge($rules, static::getTermsValidationRules($request));
        return $rules;
    }

    public static function step3Rules(): array
    {
        return [
            'type'           => 'required|integer',
            'address'        => 'required|string',
            'postcode'       => 'required|string',
            'registration'   => 'required|boolean',
            'availableFrom' => 'required|date',
            'availableTo'   => 'nullable|date|after_or_equal:available_from',
        ];
    }

    public static function step4Rules(): array
    {
        return [
            'size'           => 'required|string',
            'rent'           => 'required|numeric|min:1',
            'bills'          => 'required',
            'flatmates'      => 'nullable',
            'description'    => 'required',
            'petsAllowed'   => 'nullable|boolean',
            'smokingAllowed' => 'nullable|boolean',
            'furnishedType' => 'required|integer',
            'sharedSpace'   => 'nullable|string',
            'bathrooms'      => 'required|integer',
            'toilets'        => 'required|integer',
            'amenities'      => 'nullable|string',
        ];
    }

    public static function step5Rules(): array
    {
        return [
            'images' => 'required',
        ];
    }

    /**
     * @param \Illuminate\Http\Request|null $request Used for domain-based terms validation in step 2.
     */
    public static function submitRules($request = null): array
    {
        return array_merge(
            self::step1Rules(),
            self::step2Rules($request),
            ['images' => 'required'],
            self::step4Rules()
        );
    }

    public static function messages(): array
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
