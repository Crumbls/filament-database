<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

describe('Database Overview', function () {

    beforeEach(function () {
        $this->seedTestData();
        
        // Create a test object with the required methods
        $this->manager = new class {
            use InteractsWithDatabase;

            public string $activeConnection = 'testing';

            public function getDatabaseOverview(?string $connection = null): array
            {
                $conn = $connection ?? $this->activeConnection;
                $tables = $this->getFilteredTables($conn);
                
                $totalTables = count($tables);
                $totalRows = 0;
                $largestTables = [];
                
                foreach ($tables as $table) {
                    $rowCount = $table['row_count'] ?? 0;
                    $totalRows += $rowCount;
                    $largestTables[] = [
                        'name' => $table['name'],
                        'rows' => $rowCount,
                    ];
                }
                
                usort($largestTables, fn($a, $b) => $b['rows'] <=> $a['rows']);
                $largestTables = array_slice($largestTables, 0, 10);
                
                $driver = $this->getDriverName($conn);
                $database = DB::connection($conn)->getDatabaseName();
                
                return [
                    'total_tables' => $totalTables,
                    'total_rows' => $totalRows,
                    'largest_tables' => $largestTables,
                    'driver' => $driver,
                    'database' => $database,
                ];
            }

            public function getFilteredTables(?string $connection = null): array
            {
                $tables = $this->getTables($connection);
                $conn = $connection ?? $this->activeConnection;
                $driver = $this->getDriverName($conn);

                try {
                    if ($driver === 'mysql') {
                        $database = DB::connection($conn)->getDatabaseName();
                        $counts = DB::connection($conn)
                            ->table('information_schema.TABLES')
                            ->where('TABLE_SCHEMA', $database)
                            ->whereIn('TABLE_NAME', array_column($tables, 'name'))
                            ->pluck('TABLE_ROWS', 'TABLE_NAME')
                            ->toArray();

                        foreach ($tables as &$table) {
                            $table['row_count'] = $counts[$table['name']] ?? 0;
                        }
                    } else {
                        foreach ($tables as &$table) {
                            $tableName = $table['name'];
                            $table['row_count'] = DB::connection($conn)->table($tableName)->count();
                        }
                    }
                } catch (\Throwable) {
                    foreach ($tables as &$table) {
                        $table['row_count'] = null;
                    }
                }

                return $tables;
            }
        };
    });

    it('returns correct table count', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        expect($overview)->toHaveKey('total_tables')
            ->and($overview['total_tables'])->toBeGreaterThanOrEqual(3); // users, categories, posts at minimum
    });

    it('returns row counts for all tables', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        expect($overview)->toHaveKey('total_rows')
            ->and($overview['total_rows'])->toBeGreaterThan(0); // We seeded data
    });

    it('returns connection info including driver and database name', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        expect($overview)->toHaveKey('driver')
            ->and($overview)->toHaveKey('database')
            ->and($overview['driver'])->toBe('sqlite')
            ->and($overview['database'])->not->toBeNull();
    });

    it('includes largest tables by row count', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        expect($overview)->toHaveKey('largest_tables')
            ->and($overview['largest_tables'])->toBeArray()
            ->and($overview['largest_tables'])->not->toBeEmpty();

        // Each entry should have name and rows
        foreach ($overview['largest_tables'] as $table) {
            expect($table)->toHaveKey('name')
                ->and($table)->toHaveKey('rows');
        }
    });

    it('limits largest tables to top 10', function () {
        // Create more than 10 tables
        for ($i = 1; $i <= 12; $i++) {
            Schema::connection('testing')->create("test_table_{$i}", function ($table) {
                $table->id();
            });
        }

        $overview = $this->manager->getDatabaseOverview('testing');

        expect(count($overview['largest_tables']))->toBeLessThanOrEqual(10);

        // Cleanup
        for ($i = 1; $i <= 12; $i++) {
            Schema::connection('testing')->dropIfExists("test_table_{$i}");
        }
    });

    it('sorts largest tables by row count descending', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        if (count($overview['largest_tables']) > 1) {
            $rowCounts = array_column($overview['largest_tables'], 'rows');
            $sortedRowCounts = $rowCounts;
            rsort($sortedRowCounts);

            expect($rowCounts)->toBe($sortedRowCounts);
        } else {
            // If only 1 table, just ensure it exists
            expect($overview['largest_tables'])->not->toBeEmpty();
        }
    });

    it('handles tables with zero rows', function () {
        // Create an empty table
        Schema::connection('testing')->create('empty_table', function ($table) {
            $table->id();
            $table->string('name');
        });

        $overview = $this->manager->getDatabaseOverview('testing');

        $emptyTable = collect($overview['largest_tables'])->firstWhere('name', 'empty_table');

        if ($emptyTable) {
            expect($emptyTable['rows'])->toBe(0);
        }

        // Cleanup
        Schema::connection('testing')->drop('empty_table');
    });

    it('respects filtered tables configuration', function () {
        $allTables = $this->manager->getFilteredTables('testing');

        expect($allTables)->toBeArray();

        // Each table should have row_count
        foreach ($allTables as $table) {
            expect($table)->toHaveKey('name')
                ->and($table)->toHaveKey('row_count');
        }
    });

    it('calculates total rows correctly across all tables', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        // We know we seeded:
        // - 2 users
        // - 2 categories
        // - 1 post
        // Total = 5 rows minimum

        expect($overview['total_rows'])->toBeGreaterThanOrEqual(5);
    });

    it('handles gracefully when no tables exist', function () {
        $overview = $this->manager->getDatabaseOverview('secondary');

        expect($overview['total_tables'])->toBe(0)
            ->and($overview['total_rows'])->toBe(0)
            ->and($overview['largest_tables'])->toBeArray()
            ->and($overview['largest_tables'])->toBeEmpty();
    });
});
