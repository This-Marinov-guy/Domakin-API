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
        'deposit',
    ];

    protected $casts = [
        'bills'          => 'integer',
        'flatmates'      => 'array',
        'period'         => 'array',
        'description'    => 'array',
        'registration'   => 'boolean',
        'pets_allowed'   => 'boolean',
        'smoking_allowed' => 'boolean',
        'size'           => 'integer',
        'deposit'        => 'integer',
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
            'size'           => 'required|integer|min:1',
            'rent'           => 'required|numeric|min:1',
            'bills'          => 'nullable|integer',
            'flatmates'      => 'nullable',
            'description'    => 'required',
            'petsAllowed'   => 'nullable|boolean',
            'smokingAllowed' => 'nullable|boolean',
            'furnishedType' => 'required|integer',
            'sharedSpace'   => 'nullable|string',
            'amenities'      => 'nullable|string',
            'bathrooms'      => 'required|integer',
            'toilets'        => 'required|integer',
            'deposit'        => 'nullable|integer',
        ];
    }

    public static function step5Rules(): array
    {
        return [
            'images' => 'required_without:new_images|nullable|string',
            'new_images' => 'required_without:images|nullable|array',
            'new_images.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp,heic,heif,mp4,mov,avi,wmv,flv,mkv,webm,gif|max:5120',
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
            self::step5Rules(),
            self::step4Rules()
        );
    }

    public static function messages(): array
    {
        return [
            'email.email' => [
                'tag' => 'account:authentication.errors.email_invalid',
            ],
            'phone.min.string' => [
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
