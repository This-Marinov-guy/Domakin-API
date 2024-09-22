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
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'approved',
        'language',
    ];

    // public static function validate(array $data)
    // {
    //     return Validator::make($data, [
    //         'content' => 'required|string|max:200|min:10',
    //     ]);
    // }

    // public function rules(Request $request)
    // {
    //     $validate = $request->validate([

    //         'content' => 'required|string|max:200|min:10',

    //     ])
    // }
}
