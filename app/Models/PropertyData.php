<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyData extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'city',
        'address',
        'size',
        'period',
        'title',
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
