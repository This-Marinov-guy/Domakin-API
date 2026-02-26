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
            'availableTo'   => '2026-03-31',
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
            'amenities'      => '1,2,4',
            'flatmates'      => '1,2',
            'sharedSpace'    => '1,2,4',
            'description'    => ['en' => 'Wow what a nice room'],
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

    /** Empty body for submit (e.g. missing referenceId validation). */
    protected function submitDataEmpty(): array
    {
        return [];
    }

    // ---------------------------------------------------------------
    // Direct controller tests: attributes for ListingApplication::create(...)
    // ---------------------------------------------------------------

    protected function applicationAttrsForStep3(array $overrides = []): array
    {
        return array_merge(['step' => 3], $overrides);
    }

    protected function applicationAttrsForStep4(array $overrides = []): array
    {
        return array_merge(['step' => 4], $overrides);
    }

    protected function applicationAttrsForStep5(array $overrides = []): array
    {
        return array_merge(['step' => 5], $overrides);
    }

    protected function applicationAttrsForStep5WithImages(array $overrides = []): array
    {
        return array_merge([
            'step'   => 5,
            'images' => 'https://example.com/image.jpg',
        ], $overrides);
    }

    protected function applicationAttrsForShow(array $overrides = []): array
    {
        return array_merge(['email' => 'example@example.com'], $overrides);
    }

    /** Minimal application that fails submit validation (missing size/rent/bills/deposit). */
    protected function applicationAttrsForSubmitIncomplete(array $overrides = []): array
    {
        return array_merge(['step' => 3], $overrides);
    }

    /**
     * Prod-like application attributes for successful submit (real PropertyService encodes JSON).
     * Flatmates/description stored so model returns strings for property_data insert.
     */
    protected function applicationAttrsForSubmitSuccess(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'Vladislav',
            'surname'         => 'Admin',
            'email'           => 'vlady1002@abv.bg',
            'phone'           => '+93 312312',
            'type'            => 1,
            'city'            => 'Amsterdam',
            'address'         => 'Herengracht 1',
            'postcode'        => '1015 BZ',
            'size'            => 25,
            'rent'            => '850',
            'bills'           => 80,
            'deposit'         => 500,
            'registration'    => true,
            'pets_allowed'    => false,
            'smoking_allowed' => false,
            'furnished_type'  => 1,
            'bathrooms'       => 1,
            'toilets'         => 1,
            'available_from'  => '2026-03-01',
            'available_to'    => '2026-03-31',
            'shared_space'    => '1,2',
            'amenities'       => '7,10',
            'flatmates'       => '0,1',
            'description'     => 'Wow what a nice room',
            'images'          => 'https://example.com/image.jpg',
            'step'            => 5,
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
