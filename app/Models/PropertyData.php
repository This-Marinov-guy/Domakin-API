<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyData extends Model
{
    use HasFactory;

    protected $fillable = [
        'city',
        'address',
        'size',
        'period',
        'rent',
        'bills',
        'flatmates',
        'registration',
        'description',
        'images',
    ]; 

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

}
