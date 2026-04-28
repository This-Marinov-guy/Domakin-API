<?php

namespace App\Services;

use App\Models\Viewing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class ViewingService
{
    /**
     * Validation rules for editing a viewing (status, internal_note).
     *
     * @return array<string, string>
     */
    public static function editRules(): array
    {
        return [
            'id' => 'required|integer|exists:viewings,id',
            'status' => 'nullable|integer',
            'internal_note' => 'nullable|string',
        ];
    }

    public static function validateEdit(array $data): Validator
    {
        return ValidatorFacade::make($data, self::editRules());
    }

    /**
     * @param  array{id: int, status?: int, internal_note?: string}  $data
     */
    public function updateViewing(array $data, ?string $updatedByUserId): ?Viewing
    {
        $viewing = Viewing::find($data['id'] ?? null);
        if (!$viewing) {
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
            $viewing->update($updates);
        }

        return $viewing->fresh(['internalUpdatedBy']);
    }
}
