<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
