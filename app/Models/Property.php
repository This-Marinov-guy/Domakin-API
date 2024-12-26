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

    public static function rules(): array
    {
        return [
            'personalData.name' => 'required|string',
            'personalData.surname' => 'required|string',
            'personalData.email' => 'required|email',
            'personalData.phone' => 'required|string',

            'propertyData.city' => 'required|string',
            'propertyData.address' => 'required|string',
            'propertyData.size' => 'required|string',
            'propertyData.period' => 'required|string',
            'propertyData.rent' => 'required|string',
            'propertyData.bills' => 'required|string',
            'propertyData.flatmates' => 'required|string',
            'propertyData.registration' => 'required|string',
            'propertyData.description' => 'required|string',
            'images' => 'required|array',

            'terms.contact' => 'required|boolean',
            'terms.legals' => 'required|boolean',

        ];
    }
}