<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralBonus extends Model
{
    public const STATUS_WAITING_APPROVAL = 1;
    public const STATUS_PENDING          = 2;
    public const STATUS_COMPLETED        = 3;
    public const STATUS_REJECTED         = 4;

    public const TYPE_LISTING = 1;
    public const TYPE_VIEWING = 2;
    public const TYPE_RENTING = 3;

    protected $fillable = [
        'referral_code',
        'amount',
        'status',
        'type',
        'reference_id',
        'public_note',
        'internal_note',
        'metadata',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'status'   => 'integer',
        'type'     => 'integer',
        'metadata' => 'array',
    ];
}
