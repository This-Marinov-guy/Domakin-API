<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'viewings',
        'search_rentings',
        'rentings',
        'careers',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'locale')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('locale', 10)->default('en');
                });
            }

            DB::table($tableName)
                ->whereNull('locale')
                ->orWhere('locale', '')
                ->update(['locale' => 'en']);

            $this->enforceEnglishDefault($tableName);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'locale')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('locale');
                });
            }
        }
    }

    private function enforceEnglishDefault(string $tableName): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "locale" SET DEFAULT \'en\'', $tableName));
            DB::statement(sprintf('ALTER TABLE "%s" ALTER COLUMN "locale" SET NOT NULL', $tableName));
            return;
        }

        if ($driver === 'mysql') {
            DB::statement(sprintf('ALTER TABLE `%s` MODIFY `locale` varchar(10) NOT NULL DEFAULT \'en\'', $tableName));
        }
    }
};
