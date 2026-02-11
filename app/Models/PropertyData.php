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
        'postcode',
        'pets_allowed',
        'smoking_allowed',
        'size',
        'period',
        'title',
        'rent',
        'bills',
        'flatmates',
        'registration',
        'description',
        'folder',
        'images',
        'payment_link',
        'type',
        'furnished_type',
        'shared_space',
        'bathrooms',
        'toilets',
        'amenities',
        'available_from',
        'available_to',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

}
