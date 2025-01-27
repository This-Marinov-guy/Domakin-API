<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rules\Password;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The default values.
     *
     * @var array<int, string>
     */
    protected $attributes = [
        'roles' => 'user',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'phone' => 'required|string|unique:users,phone',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
            'password_confirmation' => 'required|same:password',
            'terms' => 'required|boolean',
        ];
    }

    public static function messages(): array
    {
        return [
            'email.email' => [
                'tag' => 'authentication.errors.email',
            ],
            'email.unique' => [
                'tag' => 'authentication.errors.email_exists',
            ],
            'phone.unique' => [
                'tag' => 'authentication.errors.phone_exists',
            ],
            'password' => [
                'tag' => 'authentication.errors.password',
            ],
            'password_confirmation' => [
                'tag' => 'authentication.errors.password_confirmation',
            ],
        ];
    }
}
