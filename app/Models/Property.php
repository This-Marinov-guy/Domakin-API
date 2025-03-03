<?php

// app/Models/Property.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'personal_data',
        'property_data'
    ];

    protected $casts = [
        'personal_data' => 'array',
        'property_data' => 'array'
    ];

    /**
     * The default values.
     *
     * @var array<int, string>
     */
    protected $attributes = [
        'approved' => false,
        'status' => 1
    ];

    public function PersonalData()
    {
        return $this->hasOne(PersonalData::class, 'properties_id');
    }

    public function PropertyData()
    {
        return $this->hasOne(PropertyData::class, 'properties_id');
    }

    // Helper method to set data
    public function setData(array $data)
    {
        $this->personal_data = $data['personalData'] ?? [];
        $this->property_data = $data['propertyData'] ?? [];
        return $this;
    }

    public function getFormattedData()
    {
        return [
            'personalData' => $this->personal_data,
            'propertyData' => $this->property_data
        ];
    }
}