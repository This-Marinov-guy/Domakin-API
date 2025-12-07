<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the random profile image
        $this->attributes['profile_image'] = '/assets/img/dashboard/avatar_0' . mt_rand(1, 5) . '.jpg';
    }

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
        'referral_code',
    ];

    /**
     * The default values.
     *
     * @var array<int, string>
     */
    protected $attributes = [
        // profile image set in the constructor 
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
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function rules(): array
    {
        return [
            'name' => 'required|string',
            'phone' => 'required|string|min:6|unique:users,phone',
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

    public static function rulesEdit($userId = null): array
    {
        if ($userId) {
            return [
                'name' => 'required|string',
                'phone' => [
                    'required',
                    'string',
                    'min:6',
                    Rule::unique('users', 'phone')->ignore($userId),
                ],
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users', 'email')->ignore($userId),
                ],
            ];
        }

        return [
            'name' => 'required|string',
            'phone' => 'required|string|min:6|unique:users,phone',
            'email' => 'required|unique:users,email|email',
        ];
    }

    public static function rulesPassword(): array
    {
        return [
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
        ];
    }

    public static function messages(): array
    {
        return [
            'email.email' => [
                'tag' => 'account:authentication.errors.email_invalid',
            ],
            'email.unique' => [
                'tag' => 'account:authentication.errors.email_exists',
            ],
            'phone.unique' => [
                'tag' => 'account:authentication.errors.phone_exists',
            ],
            'phone.min' => [
                'tag' => 'account:authentication.errors.phone_invalid',
            ],
            'terms.accepted' => [
                'tag' => 'account:authentication.errors.terms_must_be_accepted',
            ],
            'password' => [
                'tag' => 'account:authentication.errors.password',
            ],
            'password_confirmation' => [
                'tag' => 'account:authentication.errors.password_confirmation',
            ],
        ];
    }

    public function userProperties()
    {
        return $this->hasMany(Property::class);
    }

    public function lastUpdatedProperty()
    {
        return $this->hasMany(Property::class);
    }
}
