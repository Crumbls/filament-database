<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Illuminate\Support\Facades\Schema;

describe('Schema Operations', function () {

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

    it('views table structure with column details', function () {
        $columns = $this->db->getColumns('users', 'testing');

        expect($columns)->toBeArray()
            ->and(count($columns))->toBeGreaterThanOrEqual(5);

        $nameCol = collect($columns)->firstWhere('name', 'name');
        expect($nameCol)->not->toBeNull();
    });

    it('views indexes for a table', function () {
        $indexes = $this->db->getIndexes('users', 'testing');

        expect($indexes)->toBeArray();
    });

    it('views foreign keys for posts table', function () {
        $fks = $this->db->getForeignKeys('posts', 'testing');
        expect($fks)->toBeArray();
    });

    it('adds a column to a table', function () {
        $this->db->addColumn('users', 'bio', 'text', ['nullable' => true], 'testing');

        $columns = $this->db->getColumns('users', 'testing');
        $colNames = array_column($columns, 'name');
        expect($colNames)->toContain('bio');
    });

    it('adds a column with default value', function () {
        $this->db->addColumn('users', 'status', 'string', ['nullable' => false, 'default' => 'active'], 'testing');

        $columns = $this->db->getColumns('users', 'testing');
        $statusCol = collect($columns)->firstWhere('name', 'status');
        expect($statusCol)->not->toBeNull();
    });

    it('drops a column from a table', function () {
        // SQLite has limited ALTER TABLE support, but this tests the method
        // Add a column first, then drop it
        $this->db->addColumn('categories', 'slug', 'string', ['nullable' => true], 'testing');
        expect(Schema::connection('testing')->hasColumn('categories', 'slug'))->toBeTrue();

        $this->db->dropColumn('categories', 'slug', 'testing');
        expect(Schema::connection('testing')->hasColumn('categories', 'slug'))->toBeFalse();
    });

    it('creates a new table', function () {
        $columns = [
            ['name' => 'id', 'type' => 'id', 'nullable' => false, 'primary' => false, 'autoIncrement' => false, 'arguments' => []],
            ['name' => 'title', 'type' => 'string', 'nullable' => false, 'arguments' => []],
            ['name' => 'content', 'type' => 'text', 'nullable' => true, 'arguments' => []],
        ];

        $this->db->createTable('articles', $columns, 'testing');
        expect(Schema::connection('testing')->hasTable('articles'))->toBeTrue();

        $cols = $this->db->getColumns('articles', 'testing');
        $colNames = array_column($cols, 'name');
        expect($colNames)->toContain('id')
            ->and($colNames)->toContain('title')
            ->and($colNames)->toContain('content');
    });

    it('drops a table', function () {
        Schema::connection('testing')->create('temp_table', function ($table) {
            $table->id();
            $table->string('name');
        });

        expect(Schema::connection('testing')->hasTable('temp_table'))->toBeTrue();

        $this->db->dropTable('temp_table', 'testing');
        expect(Schema::connection('testing')->hasTable('temp_table'))->toBeFalse();
    });

    it('truncates a table', function () {
        $this->seedTestData();

        expect(\Illuminate\Support\Facades\DB::connection('testing')->table('categories')->count())->toBe(2);

        $this->db->truncateTable('categories', 'testing');
        expect(\Illuminate\Support\Facades\DB::connection('testing')->table('categories')->count())->toBe(0);
    });

    it('renames a table', function () {
        Schema::connection('testing')->create('old_name', function ($table) {
            $table->id();
        });

        $this->db->renameTable('old_name', 'new_name', 'testing');

        expect(Schema::connection('testing')->hasTable('old_name'))->toBeFalse()
            ->and(Schema::connection('testing')->hasTable('new_name'))->toBeTrue();

        // cleanup
        Schema::connection('testing')->drop('new_name');
    });
});
