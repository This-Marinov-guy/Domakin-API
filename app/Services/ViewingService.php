<?php

namespace App\Services;

use App\Models\User;
use App\Models\Viewing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class ViewingService
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

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

        $hasInternalNote = Schema::hasColumn('viewings', 'internal_note');
        $hasInternalUpdatedAt = Schema::hasColumn('viewings', 'internal_updated_at');
        $hasInternalUpdatedBy = Schema::hasColumn('viewings', 'internal_updated_by');
        $validUpdaterId = null;

        if ($hasInternalUpdatedBy && $this->isUuid($updatedByUserId)) {
            $validUpdaterId = User::query()->whereKey($updatedByUserId)->value('id');
        }

        $updates = [];
        if (array_key_exists('status', $data)) {
            $updates['status'] = $data['status'];
        }
        if ($hasInternalNote && array_key_exists('internal_note', $data)) {
            $updates['internal_note'] = $data['internal_note'];
        }

        if ($updates !== []) {
            if ($hasInternalUpdatedAt) {
                $updates['internal_updated_at'] = now();
            }
            if ($hasInternalUpdatedBy) {
                $updates['internal_updated_by'] = $validUpdaterId;
            }
            $viewing->update($updates);
        }

        // Avoid eager-loading the updater relation here because older production
        // rows may still contain non-UUID values such as 0.
        return $viewing->fresh();
    }

    private function isUuid(?string $value): bool
    {
        return is_string($value) && preg_match(self::UUID_PATTERN, $value) === 1;
    }
}
