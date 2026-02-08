<?php

namespace App\Models;

use App\Models\Concerns\HasDomainBasedTermsValidation;
use App\Services\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renting extends Model
{
    use HasFactory, HasDomainBasedTermsValidation;

    protected $table = 'rentings';

    protected $appends = [
        'internal_updated_by_name',
        'internal_updated_by_surname',
        'property_title',
    ];

    protected $fillable = [
        'property_id',
        'property',
        'name',
        'surname',
        'phone',
        'email',
        'letter',
        'note',
        'referral_code',
        'interface',
        'status',
        'internal_note',
        'internal_updated_at',
        'internal_updated_by',
    ];

    protected $casts = [
        'internal_updated_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function internalUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_updated_by');
    }

    public function getInternalUpdatedByNameAttribute(): ?string
    {
        return $this->internalUpdatedBy?->name;
    }

    public function getInternalUpdatedBySurnameAttribute(): ?string
    {
        return $this->internalUpdatedBy?->surname ?? null;
    }

    public function getPropertyTitleAttribute(): string
    {
        $property = $this->getRelationValue('property');
        if (!$property instanceof Property) {
            return '';
        }
        $title = $property->propertyData?->title ?? null;
        if ($title === null || $title === '') {
            return '';
        }
        if (is_string($title)) {
            $decoded = json_decode($title, true);
            if (is_array($decoded)) {
                return (string) (Helpers::getTranslatedValue($decoded, 'en', true, '') ?: '');
            }
            return $title;
        }
        return is_array($title) ? (string) (Helpers::getTranslatedValue($title, 'en', true, '') ?: '') : '';
    }

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
