<?php

namespace Tests\Feature\ListingApplication;

use Illuminate\Support\Arr;

trait ListingApplicationData
{
    // ---------------------------------------------------------------
    // Step 2 — personal info
    // ---------------------------------------------------------------

    protected function step2Data(array $overrides = []): array
    {
        return array_merge([
            'step'    => 2,
            'name'    => 'Vladislav',
            'surname' => 'Admin',
            'email'   => 'vlady1002@abv.bg',
            'phone'   => '+31612345678',
            'terms'   => ['contact' => true, 'legals' => true],
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Step 3 — property location
    // ---------------------------------------------------------------

    protected function step3Data(string $referenceId, array $overrides = []): array
    {
        return array_merge([
            'referenceId'   => $referenceId,
            'type'          => 1,
            'address'       => 'Herengracht 1',
            'postcode'      => '1015 BZ',
            'registration'  => true,
            'availableFrom' => '2026-03-01',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Step 4 — pricing & details
    // ---------------------------------------------------------------

    protected function step4Data(string $referenceId, array $overrides = []): array
    {
        return array_merge([
            'referenceId'    => $referenceId,
            'size'           => 25,
            'rent'           => 850,
            'bills'          => 80,
            'deposit'        => 500,
            'furnishedType'  => 1,
            'bathrooms'      => 1,
            'toilets'        => 1,
            'description'    => ['en' => 'Nice room'],
            'petsAllowed'    => false,
            'smokingAllowed' => false,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Step 5 — images
    // ---------------------------------------------------------------

    protected function step5Data(string $referenceId, array $overrides = []): array
    {
        return array_merge([
            'referenceId' => $referenceId,
            'images'      => 'https://example.com/image.jpg',
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Save draft
    // ---------------------------------------------------------------

    protected function saveData(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Vladislav',
            'surname'        => 'Admin',
            'email'          => 'vlady1002@abv.bg',
            'phone'          => '+31612345678',
            'step'           => 1,
            'petsAllowed'    => false,
            'smokingAllowed' => false,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Edit
    // ---------------------------------------------------------------

    protected function editData(int|string $id, array $overrides = []): array
    {
        return array_merge(['id' => $id], $overrides);
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    protected function deleteData(int|string $id): array
    {
        return ['id' => $id];
    }

    // ---------------------------------------------------------------
    // Submit
    // ---------------------------------------------------------------

    protected function submitData(string $referenceId): array
    {
        return ['referenceId' => $referenceId];
    }

    // ---------------------------------------------------------------
    // Helper: remove keys from a payload (for missing-field tests)
    // ---------------------------------------------------------------

    protected function without(array $data, string ...$keys): array
    {
        return Arr::except($data, $keys);
    }
}
