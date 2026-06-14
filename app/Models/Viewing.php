<?php

namespace App\Models;

use App\Models\Concerns\HasDomainBasedTermsValidation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Viewing extends Model
{
    use HasFactory, HasDomainBasedTermsValidation;

    protected $table = 'viewings';

    protected $appends = [
        'internal_updated_by_user',
    ];

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
        'status',
        'internal_note',
        'internal_updated_at',
        'internal_updated_by',
    ];

    protected $attributes = [
        'status' => 1
    ];

    protected $casts = [
        'internal_updated_at' => 'datetime',
    ];

    protected $hidden = [
        'internalUpdatedBy',
    ];

    public function internalUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_updated_by');
    }

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

    protected function hasValidInternalUpdatedByUuid(): bool
    {
        $value = $this->attributes['internal_updated_by'] ?? null;
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return false;
        }

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $value
        );
    }

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
            'note' => 'required|string',
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
            'note.required' => [
                'tag' => 'viewing.errors.questions_required',
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
