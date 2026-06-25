<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';

    protected $fillable = [
        'source_key',
        'spreadsheet_id',
        'sheet_gid',
        'sheet_row_number',
        'name',
        'email',
        'phone',
        'data',
        'source_row_hash',
        'synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'synced_at' => 'datetime',
    ];
}
