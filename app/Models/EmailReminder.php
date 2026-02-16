<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailReminder extends Model
{
    protected $table = 'email_reminders';

    protected $fillable = [
        'email',
        'scheduled_date',
        'metadata',
        'template_id',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_date' => 'date',
    ];
}
