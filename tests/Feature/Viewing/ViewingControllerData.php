<?php

namespace Tests\Feature\Viewing;

use App\Models\Viewing;
use Illuminate\Support\Arr;

trait ViewingControllerData
{
    // ---------------------------------------------------------------
    // DB helpers
    // ---------------------------------------------------------------

    protected function createViewing(array $overrides = []): Viewing
    {
        return Viewing::create(array_merge([
            'name'      => 'John',
            'surname'   => 'Doe',
            'phone'     => '+31612345678',
            'email'     => 'john@example.com',
            'city'      => 'Amsterdam',
            'address'   => 'Herengracht 1',
            'date'      => '01-06-2027',
            'time'      => '14:00',
            'interface' => 'web',
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Request payloads
    // ---------------------------------------------------------------

    /**
     * Valid POST /api/v1/viewing/create payload.
     * Keys are sent as-is; ViewingController passes them through
     * Helpers::camelToSnakeObject before validation.
     * Date must be in d-m-Y format for Carbon parsing in the controller.
     */
    protected function viewingCreateData(array $overrides = []): array
    {
        return array_merge([
            'name'      => 'John',
            'surname'   => 'Doe',
            'phone'     => '+31612345678',
            'email'     => 'john@example.com',
            'city'      => 'Amsterdam',
            'address'   => 'Herengracht 1',
            'date'      => '01-06-2027',  // d-m-Y — required by Carbon::createFromFormat
            'time'      => '14:00',
            'interface' => 'web',
            // ViewingController uses $request->all() directly (no json_decode), so pass as array.
            // terms always required when localhost is in terms_required_domains config.
            'terms'     => ['contact' => true, 'legals' => true],
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
