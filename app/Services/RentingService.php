<?php

namespace App\Services;

use App\Models\Renting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class RentingService
{
    /**
     * Validation rules for editing a renting (status, internal_note).
     *
     * @return array<string, string>
     */
    public static function editRules(): array
    {
        return [
            'id' => 'required|integer|exists:rentings,id',
            'status' => 'nullable|integer',
            'internal_note' => 'nullable|string',
        ];
    }

    /**
     * Validate edit request data.
     */
    public static function validateEdit(array $data): Validator
    {
        return ValidatorFacade::make($data, self::editRules());
    }

    /**
     * Update renting status and/or internal_note; set internal_updated_at and internal_updated_by when changed.
     *
     * @param  array{id: int, status?: int, internal_note?: string}  $data
     * @param  string|null  $updatedByUserId
     * @return Renting|null  Updated renting with relations, or null if not found
     */
    public function updateRenting(array $data, ?string $updatedByUserId): ?Renting
    {
        $renting = Renting::find($data['id'] ?? null);
        if (!$renting) {
            return null;
        }

        $updates = [];
        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
        }
        if (array_key_exists('internal_note', $data)) {
            $updates['internal_note'] = $data['internal_note'];
        }

        if ($updates !== []) {
            $updates['internal_updated_at'] = now();
            $updates['internal_updated_by'] = $updatedByUserId;
            $renting->update($updates);
        }

        return $renting->fresh(['property.propertyData']);
    }
}
