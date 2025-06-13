<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;

class Feedback extends Model
{
    use HasFactory, Notifiable;

    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'feedbacks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'content',
    ];

    /**
     * The default values.
     *
     * @var array<int, string>
     */
    protected $attributes = [
        'name' => 'Anonymous',
        'approved' => false,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'approved',
        'language',
    ];

    public static function rules()
    {
        return [
            'name' => 'nullable|string|max:255',
            'content' => 'required|string|max:1000',
            'language' => 'nullable|string|max:10',
        ];
    }

    public static function messages()
    {
        return [
            'content' => [
                'tag' => 'The content must not be empty or exceed 1000 characters.',
            ]
        ];
    }
}
