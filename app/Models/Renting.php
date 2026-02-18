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
        'internal_updated_by_user',
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

    /** Hide full user relation from JSON; use internal_updated_by_user (id + name) instead */
    protected $hidden = [
        'internalUpdatedBy',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function internalUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_updated_by');
    }

    /**
     * Get the User model from the relation (without going through the appended attribute).
     */
    protected function getInternalUpdatedByUser(): ?User
    {
        if (!$this->hasValidInternalUpdatedByUuid()) {
            return null;
        }
        $user = $this->getRelationValue('internalUpdatedBy');
        if ($user === null) {
            $user = $this->internalUpdatedBy()->first();
        }
        return $user instanceof User ? $user : null;
    }

    /**
     * Appended object with only id and name (for serialization). Named _user to avoid shadowing the internal_updated_by FK used by the relation.
     */
    public function getInternalUpdatedByUserAttribute(): ?array
    {
        $user = $this->getInternalUpdatedByUser();
        if (!$user) {
            return null;
        }
        return [
            'id' => $user->id,
            'name' => $user->name ?? null,
        ];
    }

    /**
     * Whether internal_updated_by is a valid UUID (so loading the relation won't cause uuid = integer).
     */
    protected function hasValidInternalUpdatedByUuid(): bool
    {
        $value = $this->attributes['internal_updated_by'] ?? null;
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return false;
        }
        $str = (string) $value;
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str);
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
            'letter' => 'nullable|file|mimes:pdf,doc,docx|max:4120',
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
