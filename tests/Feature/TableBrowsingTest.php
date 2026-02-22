<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Crumbls\FilamentDatabase\FilamentDatabasePlugin;

describe('Table Browsing', function () {

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
    });

    it('lists tables for the testing connection', function () {
        $tables = $this->db->getTables('testing');

        $tableNames = array_column($tables, 'name');
        expect($tableNames)->toContain('users')
            ->and($tableNames)->toContain('posts')
            ->and($tableNames)->toContain('categories');
    });

    it('filters hidden tables via plugin', function () {
        $plugin = (new FilamentDatabasePlugin())->hideTables(['categories']);

        $tables = $this->db->getTables('testing');
        $filtered = array_filter($tables, fn($t) => $plugin->isTableVisible($t['name']));
        $names = array_column($filtered, 'name');

        expect($names)->not->toContain('categories')
            ->and($names)->toContain('users');
    });

    it('shows only whitelisted tables via plugin', function () {
        $plugin = (new FilamentDatabasePlugin())->showOnlyTables(['users']);

        $tables = $this->db->getTables('testing');
        $filtered = array_filter($tables, fn($t) => $plugin->isTableVisible($t['name']));
        $names = array_column($filtered, 'name');

        expect($names)->toContain('users')
            ->and($names)->not->toContain('posts')
            ->and($names)->not->toContain('categories');
    });

    it('gets column info for a table', function () {
        $columns = $this->db->getColumns('users', 'testing');
        $colNames = array_column($columns, 'name');

        expect($colNames)->toContain('id')
            ->and($colNames)->toContain('name')
            ->and($colNames)->toContain('email')
            ->and($colNames)->toContain('password')
            ->and($colNames)->toContain('is_admin');
    });

    it('gets indexes for a table', function () {
        $indexes = $this->db->getIndexes('users', 'testing');

        expect($indexes)->toBeArray()
            ->and(count($indexes))->toBeGreaterThanOrEqual(1);
    });

    it('gets foreign keys for posts table', function () {
        $fks = $this->db->getForeignKeys('posts', 'testing');

        expect($fks)->toBeArray();
        // SQLite may or may not report FK info depending on version
        // Just ensure the method doesn't throw
    });

    it('reports the driver name', function () {
        $driver = $this->db->getDriverName('testing');
        expect($driver)->toBe('sqlite');
    });
});
