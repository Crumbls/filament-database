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

    describe('Plugin read-only controls', function () {

        it('plugin readOnly blocks non-SELECT in query runner', function () {
            $plugin = (new FilamentDatabasePlugin())->readOnly();
            expect($plugin->isQueryRunnerReadOnly())->toBeTrue();
        });

        it('plugin queryRunnerReadOnly blocks writes independently', function () {
            $plugin = (new FilamentDatabasePlugin())->queryRunnerReadOnly();
            expect($plugin->isQueryRunnerReadOnly())->toBeTrue()
                ->and($plugin->isReadOnly())->toBeFalse();
        });

        it('plugin disableQueryRunner disables runner', function () {
            $plugin = (new FilamentDatabasePlugin())->disableQueryRunner();
            expect($plugin->isQueryRunnerEnabled())->toBeFalse();
        });

        it('plugin runner enabled by default', function () {
            $plugin = new FilamentDatabasePlugin();
            expect($plugin->isQueryRunnerEnabled())->toBeTrue()
                ->and($plugin->isQueryRunnerReadOnly())->toBeFalse();
        });
    });
});
