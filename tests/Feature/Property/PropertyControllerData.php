<?php

namespace Tests\Feature\Property;

use App\Models\PersonalData;
use App\Models\Property;
use App\Models\PropertyData;
use Illuminate\Support\Arr;

trait PropertyControllerData
{
    // ---------------------------------------------------------------
    // Personal data payload (decoded array)
    // ---------------------------------------------------------------

    protected function personalDataArray(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Vladislav',
            'surname' => 'Admin',
            'email'   => 'vlady1002@abv.bg',
            'phone'   => '+31612345678',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Property data payload for create (decoded array)
    // ---------------------------------------------------------------

    protected function propertyDataArray(array $overrides = []): array
    {
        return array_merge([
            'city'            => 'Amsterdam',
            'address'         => 'Herengracht 1',
            'postcode'        => '1015 BZ',
            'pets_allowed'    => false,
            'smoking_allowed' => false,
            'size'            => 25,
            'period'          => '6 months',
            'rent'            => '850',
            'furnished_type'  => 1,
            'shared_space'    => [1, 2],
            'amenities'       => [7, 10],
            'bathrooms'       => 1,
            'toilets'         => 1,
            'available_from'  => '2026-03-01',
            'available_to'    => '2026-06-30',
            'bills'           => 80,
            'deposit'         => 500,
            'flatmates'       => '0,1',
            'registration'    => true,
            'description'     => 'Wow what a nice room',
            'type'            => 1,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Property data payload for edit (decoded array — fewer required fields)
    // ---------------------------------------------------------------

    protected function editPropertyDataArray(array $overrides = []): array
    {
        return array_merge([
            'city'         => 'Amsterdam',
            'address'      => 'Herengracht 1',
            'size'         => 25,
            'period'       => '6 months',
            'rent'         => '850',
            'bills'        => 80,
            'title'        => 'Nice room Amsterdam',
            'flatmates'    => '0,1',
            'registration' => true,
            'description'  => 'Wow what a nice room',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Full request payloads
    // ---------------------------------------------------------------

    /** Request body for POST /api/v1/property/create */
    protected function createRequestData(array $overrides = []): array
    {
        return array_merge([
            'personalData' => json_encode($this->personalDataArray()),
            'propertyData' => json_encode($this->propertyDataArray()),
            'interface'    => 'web',
            'terms'        => json_encode(['contact' => true, 'legals' => true]),
        ], $overrides);
    }

    /** Request body for POST /api/v1/property/edit */
    protected function editRequestData(int $id, array $overrides = []): array
    {
        return array_merge([
            'id'           => $id,
            'propertyData' => json_encode($this->editPropertyDataArray()),
            'status'       => 1,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // DB helpers — create a Property with all required relations
    // ---------------------------------------------------------------

    protected function createPropertyWithRelations(array $propertyOverrides = []): Property
    {
        $property = Property::create(array_merge([
            'status'    => 2,
            'is_signal' => false,
        ], $propertyOverrides));

        PersonalData::create([
            'property_id' => $property->id,
            'name'        => 'Vladislav',
            'surname'     => 'Admin',
            'email'       => 'vlady1002@abv.bg',
            'phone'       => '+31612345678',
        ]);

        // period, description, flatmates, title are JSON columns in PostgreSQL —
        // store as valid JSON strings (locale-keyed objects, as the controller does).
        PropertyData::create([
            'property_id'     => $property->id,
            'city'            => 'Amsterdam',
            'address'         => 'Herengracht 1',
            'postcode'        => '1015 BZ',
            'pets_allowed'    => false,
            'smoking_allowed' => false,
            'size'            => 25,
            'period'          => json_encode(['en' => '6 months']),
            'rent'            => '850',
            'bills'           => 80,
            'deposit'         => 500,
            'flatmates'       => json_encode(['en' => '0 flatmates']),
            'registration'    => true,
            'description'     => json_encode(['en' => 'Wow what a nice room']),
            'title'           => json_encode(['en' => 'Nice room Amsterdam']),
            'images'          => 'https://example.com/image.jpg',
            'furnished_type'  => 1,
            'bathrooms'       => 1,
            'toilets'         => 1,
            'available_from'  => '2026-03-01',
            'available_to'    => '2026-06-30',
            'type'            => 1,
        ]);

        return $property->fresh(['personalData', 'propertyData']);
    }

    // ---------------------------------------------------------------
    // Helper: remove keys from a payload (for missing-field tests)
    // ---------------------------------------------------------------

    protected function without(array $data, string ...$keys): array
    {
        return Arr::except($data, $keys);
    }
}
