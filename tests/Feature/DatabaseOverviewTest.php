<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseOverviewPage extends DatabaseManager
{
    public function bootedInteractsWithTable(): void
    {
        // These tests exercise metadata methods without rendering a Filament table.
    }

    public function render(): View
    {
        return view()->file(__DIR__ . '/../Fixtures/empty.blade.php');
    }

    public function resetTable(): void
    {
        // These tests do not render table records.
    }
}

function registerDatabaseOverviewPanel(): void
{
    $panel = Panel::make()
        ->id('database-overview')
        ->default()
        ->plugin(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing', 'secondary']),
        );

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
}

describe('Database Overview', function () {

    beforeEach(function () {
        $this->seedTestData();

        registerDatabaseOverviewPanel();

        $this->manager = app(DatabaseOverviewPage::class);
        $this->manager->activeConnection = 'testing';
    });

    it('returns correct table count', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        expect($overview)->toHaveKey('total_tables')
            ->and($overview['total_tables'])->toBeGreaterThanOrEqual(3); // users, categories, posts at minimum
    });

    it('returns row counts for all tables', function () {
        $overview = $this->manager->getDatabaseOverview('testing');

        expect($overview)->toHaveKey('total_rows')
            ->and($overview['total_rows'])->toBe(5);
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

    it('loads SQLite row counts in one statement and caches the result for the render cycle', function () {
        $connection = DB::connection('testing');
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $first = $this->manager->getFilteredTables('testing');
        $queriesAfterFirstCall = $connection->getQueryLog();
        $rowCountQueries = array_filter(
            $queriesAfterFirstCall,
            static fn (array $query): bool => str_contains($query['query'], 'row_count'),
        );

        $second = $this->manager->getFilteredTables('testing');

        expect($first)->toBe($second)
            ->and($rowCountQueries)->toHaveCount(1)
            ->and($connection->getQueryLog())->toHaveCount(count($queriesAfterFirstCall))
            ->and(collect($first)->pluck('row_count', 'name')->all())->toMatchArray([
                'users' => 2,
                'categories' => 2,
                'posts' => 1,
            ]);
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
