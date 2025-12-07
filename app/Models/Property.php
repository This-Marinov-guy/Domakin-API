<?php

// app/Models/Property.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends Model
{
    protected $fillable = [
        'personal_data',
        'property_data',
        'created_by',
        'last_updated_by',
        'approved',
        'status',
        'release_timestamp',
        'referral_code',
    ];

    protected $casts = [
        'personal_data' => 'array',
        'property_data' => 'array'
    ];

    /**
     * The default values.
     *
     * @var array<int, string>
     */
    protected $attributes = [
        'approved' => false,
        'status' => 1
    ];

    public function personalData()
    {
        return $this->hasOne(PersonalData::class);
    }

    public function propertyData()
    {
        return $this->hasOne(PropertyData::class);
    }

    public function propertyCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastUpdateBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Helper method to set data
    public function setData(array $data)
    {
        if (isset($data['personalData'])) {
            $this->personal_data = $data['personalData'];
        }

        if (isset($data['propertyData'])) {
            $this->property_data = $data['propertyData'];
        }

        return $this;
    }

    public function getFormattedData()
    {
        return [
            'personalData' => $this->personal_data,
            'propertyData' => $this->property_data
        ];
    }

    public static function rules(): array
    {
        return [
            'personalData.name' => 'required|string',
            'personalData.surname' => 'required|string',
            'personalData.email' => 'required|email',
            'personalData.phone' => 'required|string|min:6',

            'propertyData.city' => 'required|string',
            'propertyData.address' => 'required|string',
            'propertyData.postcode' => 'required|string',
            'propertyData.pets_allowed' => 'required|boolean',
            'propertyData.smoking_allowed' => 'required|boolean',
            'propertyData.size' => 'required|string',
            'propertyData.period' => 'required|string',
            'propertyData.rent' => 'required|string',
            'propertyData.bills' => 'required|string',
            'propertyData.flatmates' => 'required|string',
            'propertyData.registration' => 'required|boolean',
            'propertyData.description' => 'required|string',
            'images' => 'required|array',

            'terms' => 'required|array',
            'terms.contact' => 'required|accepted',
            'terms.legals' => 'required|accepted',

        ];
    }

    public static function editRules(): array
    {
        return [
            'propertyData.city' => 'required|string',
            'propertyData.address' => 'required|string',
            'propertyData.size' => 'required|string',
            'propertyData.period' => 'required',
            'propertyData.rent' => 'required|numeric',
            'propertyData.bills' => 'required',
            'propertyData.title' => 'required',
            'propertyData.flatmates' => 'required',
            'propertyData.registration' => 'required',
            'propertyData.description' => 'required',
        ];
    }

    public static function messages(): array
    {
        return [
            'personalData.email.email' => [
                'tag' => 'account:authentication.errors.email_invalid',
            ],
            'personalData.phone.min' => [
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