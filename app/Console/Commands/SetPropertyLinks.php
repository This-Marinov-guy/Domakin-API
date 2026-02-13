<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SetPropertyLinks extends Command
{
    protected $signature = 'properties:set-links
                            {--dry-run : List properties that would be updated without writing}';

    protected $description = 'Set link on properties that have a slug (scan properties table)';

    public function handle(PropertyService $propertyService): int
    {
        if (! Schema::hasColumn('properties', 'slug')) {
            $this->error('The properties table has no "slug" column.');
            return 1;
        }

        if (! Schema::hasColumn('properties', 'link')) {
            $this->error('The properties table has no "link" column.');
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run – no changes will be written.');
        }

        $query = Property::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '');

        $count = $query->count();
        if ($count === 0) {
            $this->info('No properties with a slug found.');
            return 0;
        }

        $this->info("Found {$count} propert(ies) with slug.");

        $updated = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->with('propertyData')->chunkById(100, function ($properties) use ($propertyService, $dryRun, &$updated, $bar) {
            foreach ($properties as $property) {
                $payload = [
                    'id'   => $property->id,
                    'slug' => $property->slug,
                    'city' => $property->propertyData?->city ?? '',
                ];

                $link = $propertyService->getPropertyUrl($payload);

                if (! $dryRun) {
                    $property->link = $link;
                    $property->save();
                }

                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done. ' . ($dryRun ? "Would update {$updated} propert(ies)." : "Updated {$updated} propert(ies)."));

        return 0;
    }
}
