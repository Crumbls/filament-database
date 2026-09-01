<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Illuminate\Support\Facades\DB;

describe('SQL Runner', function () {

    beforeEach(function () {
        $this->db = new class {
            use InteractsWithDatabase;

            public string $activeTable = '';
            public string $activeConnection = 'testing';

            public function detectPrimaryKey(string $table, string $connection): ?string
            {
                return 'id';
            }

            public function queryIsReadOnly(string $sql): bool
            {
                return $this->isReadOnlySql($sql);
            }
        };
        $this->seedTestData();
    });

    it('executes SELECT query and returns results', function () {
        $results = $this->db->runQuery('SELECT * FROM users', 'testing');

        expect($results)->toBeArray()
            ->and($results)->toHaveCount(2);
    });

    it('executes SELECT with WHERE clause', function () {
        $results = $this->db->runQuery("SELECT * FROM users WHERE name = 'Alice'", 'testing');

        expect($results)->toHaveCount(1)
            ->and($results[0]->name)->toBe('Alice');
    });

    it('executes non-SELECT statements', function () {
        $results = $this->db->runQuery("INSERT INTO categories (name, description, created_at, updated_at) VALUES ('Science', 'Science posts', datetime('now'), datetime('now'))", 'testing');

        expect($results)->toBeArray();
        expect(DB::connection('testing')->table('categories')->count())->toBe(3);
    });

    it('throws on invalid SQL', function () {
        $this->db->runQuery('SELECT * FROM nonexistent_table', 'testing');
    })->throws(\Illuminate\Database\QueryException::class);

    it('caps result sets before they are hydrated into Livewire state', function () {
        $rows = [];

        for ($index = 0; $index < 550; $index++) {
            $rows[] = [
                'name' => "Category {$index}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::connection('testing')->table('categories')->insert($rows);

        expect($this->db->runQuery('SELECT * FROM categories', 'testing'))
            ->toHaveCount(500);
    });

    it('accepts only explicitly safe read-only statement shapes', function (string $sql) {
        expect($this->db->queryIsReadOnly($sql))->toBeTrue();
    })->with([
        'select' => 'SELECT * FROM users',
        'show' => 'SHOW TABLES',
        'describe' => 'DESCRIBE users',
        'explain select' => 'EXPLAIN SELECT * FROM users',
        'sqlite explain select' => 'EXPLAIN QUERY PLAN SELECT * FROM users',
        'PostgreSQL explain analyze select' => 'EXPLAIN ANALYZE SELECT * FROM users',
    ]);

    it('rejects read-only statement bypasses', function (string $sql) {
        expect($this->db->queryIsReadOnly($sql))->toBeFalse();
    })->with([
        'write' => 'DELETE FROM users',
        'write through explain analyze' => 'EXPLAIN ANALYZE DELETE FROM users',
        'stacked statement' => 'SELECT * FROM users; DELETE FROM users',
        'select into' => 'SELECT * INTO archived_users FROM users',
        'locking select' => 'SELECT * FROM users FOR UPDATE',
        'share-locking select' => 'SELECT * FROM users FOR SHARE',
    ]);

    describe('Plugin read-only controls', function () {

        it('plugin readOnly blocks non-SELECT in query runner', function () {
            $plugin = (new FilamentDatabasePlugin())->readOnly();
            expect($plugin->isQueryRunnerReadOnly())->toBeTrue();
        });

        it('plugin queryRunnerReadOnly blocks writes independently', function () {
            config()->set('filament-database.read_only', false);

            $plugin = (new FilamentDatabasePlugin())->queryRunnerReadOnly();
            expect($plugin->isQueryRunnerReadOnly())->toBeTrue()
                ->and($plugin->isReadOnly())->toBeFalse();
        });

        it('plugin disableQueryRunner disables runner', function () {
            $plugin = (new FilamentDatabasePlugin())->disableQueryRunner();
            expect($plugin->isQueryRunnerEnabled())->toBeFalse();
        });

        it('plugin runner follows its disabled configuration default', function () {
            config()->set('filament-database.query_runner', false);

            $plugin = new FilamentDatabasePlugin();

            expect($plugin->isQueryRunnerEnabled())->toBeFalse();
        });
    });
});
