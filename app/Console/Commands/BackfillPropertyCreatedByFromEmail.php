<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class BackfillPropertyCreatedByFromEmail extends Command
{
    protected $signature = 'properties:backfill-created-by
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Backfill properties.created_by by matching personal_data.email to users.email';

    public function handle(): int
    {
        if (!Schema::hasColumn('properties', 'created_by')) {
            $this->error('The properties table has no "created_by" column.');
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run – no changes will be written.');
        }

        $query = Property::query()->with('personalData');
        // Never overwrite created_by; only fill missing values
        $query->whereNull('created_by');

        $total = $query->count();
        if ($total === 0) {
            $this->info('No properties to process.');
            return 0;
        }

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $errors = 0;

        $this->info("Processing {$total} propert(ies)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($properties) use ($dryRun, &$updated, &$skipped, &$notFound, &$errors, $bar) {
            foreach ($properties as $property) {
                try {
                    $email = $property->personalData?->email;
                    if ($email === null || trim($email) === '') {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $email = strtolower(trim($email));
                    $user = User::query()
                        ->whereRaw('lower(email) = ?', [$email])
                        ->first();

                    if (!$user) {
                        $notFound++;
                        $bar->advance();
                        continue;
                    }

                    if (!$dryRun) {
                        $property->created_by = $user->id;
                        $property->save();
                    }

                    $updated++;
                    $bar->advance();
                } catch (QueryException $e) {
                    $errors++;
                    $bar->advance();
                    $this->warn("Property #{$property->id}: DB error – " . $e->getMessage());
                }
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info(
            'Done. ' .
            ($dryRun ? 'Would update' : 'Updated') . ": {$updated}, skipped: {$skipped}, no user match: {$notFound}" .
            ($errors > 0 ? ", errors: {$errors}" : '') .
            '.'
        );

        return 0;
    }
}

