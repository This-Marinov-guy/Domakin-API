<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\Renting;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class BackfillRentingPropertyId extends Command
{
    protected $signature = 'renting:backfill-property-id
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Set property_id on rentings from property column: first 4 chars as number minus 1000';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run – no changes will be written.');
        }

        /** @var iterable<int, Renting> $rentings */
        $rentings = Renting::query()->get();
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rentings as $renting) {
            try {
                $propertyId = $this->derivePropertyId($renting->property);
                if ($propertyId === null) {
                    $skipped++;
                    continue;
                }

                if (!Property::where('id', $propertyId)->exists()) {
                    $this->line("Renting #{$renting->id}: property ID {$propertyId} does not exist, skip.");
                    $skipped++;
                    continue;
                }

                if (!$dryRun) {
                    $renting->update(['property_id' => $propertyId]);
                }
                $this->line("Renting #{$renting->id}: property \"{$renting->property}\" → property_id = {$propertyId}" . ($dryRun ? ' (dry run)' : ''));
                $updated++;
            } catch (QueryException $e) {
                $errors++;
                $this->warn("Renting #{$renting->id}: skip due to DB error – " . $e->getMessage());
            }
        }

        $this->info("Done. Updated: {$updated}, skipped: {$skipped}" . ($errors > 0 ? ", errors (skipped): {$errors}" : '') . '.');
        return 0;
    }

    /**
     * First 4 chars of $property as number, minus 1000. Returns null if not applicable.
     */
    private function derivePropertyId(?string $property): ?int
    {
        if ($property === null || $property === '') {
            return null;
        }
        $first4 = substr($property, 0, 4);
        if (!ctype_digit($first4)) {
            return null;
        }
        $num = (int) $first4;
        $propertyId = $num - 1000;
        if ($propertyId < 1) {
            return null;
        }
        return $propertyId;
    }
}
