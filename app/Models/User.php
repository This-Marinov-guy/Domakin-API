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
        'id',
        'name',
        'email',
        'phone',
        'profile_image',
    ];

    /**
     * The default values.
     *
     * @var array<int, string>
     */
    protected $attributes = [
        'status' => 1,
        'roles' => 'user',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
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
            'email' => 'required|unique:users,email|email',
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
            'terms' => 'required|accepted',
        ];
    }

    public static function messages(): array
    {
        return [
            'email.email' => [
                'tag' => 'account:authentication.errors.email',
            ],
            'email.unique' => [
                'tag' => 'account:authentication.errors.email_exists',
            ],
            'phone.unique' => [
                'tag' => 'account:authentication.errors.phone_exists',
            ],
            'password' => [
                'tag' => 'account:authentication.errors.password',
            ],
            'password_confirmation' => [
                'tag' => 'account:authentication.errors.password_confirmation',
            ],
        ];
    }
}
