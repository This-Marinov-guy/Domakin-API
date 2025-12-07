<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppCredential extends Model
{
    protected $table = 'app_credentials';

    protected $fillable = [
        'domain',
        'password',
    ];

    /**
     * Find app credential by domain and password
     *
     * @param string $domain
     * @param string $password
     * @return AppCredential|null
     */
    public static function findByDomainAndPassword(string $domain, string $password): ?self
    {
        return self::where('domain', $domain)
            ->where('password', $password)
            ->first();
    }
}
