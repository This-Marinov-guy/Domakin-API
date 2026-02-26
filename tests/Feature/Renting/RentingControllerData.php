<?php

namespace Tests\Feature\Renting;

use App\Constants\Properties;
use App\Models\Property;
use App\Models\Renting;
use Illuminate\Support\Arr;

trait RentingControllerData
{
    // ---------------------------------------------------------------
    // DB helpers
    // ---------------------------------------------------------------

    protected function createProperty(): Property
    {
        return Property::create(['status' => 2, 'is_signal' => false]);
    }

    protected function createRenting(int $propertyId, array $overrides = []): Renting
    {
        return Renting::create(array_merge([
            'property_id' => $propertyId,
            'property'    => 'Test property ref',
            'name'        => 'John',
            'surname'     => 'Doe',
            'phone'       => '+31612345678',
            'email'       => 'john@example.com',
            'interface'   => 'web',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Request payloads
    // ---------------------------------------------------------------

    /**
     * Valid POST /api/v1/renting/create payload.
     * Pass $propertyId so the controller can resolve the correct DB row.
     */
    protected function rentingCreateData(int $propertyId, array $overrides = []): array
    {
        return array_merge([
            // propertyId drives the if($request->has('propertyId')) branch
            'propertyId' => $propertyId + Properties::FRONTEND_PROPERTY_ID_INDEXING,
            'property'   => 'Test property ref',
            'name'       => 'John',
            'surname'    => 'Doe',
            'phone'      => '+31612345678',
            'email'      => 'john@example.com',
            'note'       => 'Looking forward to renting',
            'interface'  => 'web',
            // terms always required when localhost is in terms_required_domains config
            'terms'      => json_encode(['contact' => true, 'legals' => true]),
        ], $overrides);
    }

    /** Valid PATCH /api/v1/renting/edit payload */
    protected function rentingEditData(int $rentingId, array $overrides = []): array
    {
        return array_merge([
            'id'            => $rentingId,
            'status'        => 2,
            'internal_note' => 'Reviewed by admin',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Helper: remove keys from a payload (for missing-field tests)
    // ---------------------------------------------------------------

    protected function without(array $data, string ...$keys): array
    {
        return Arr::except($data, $keys);
    }
}
