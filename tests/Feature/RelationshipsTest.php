<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;

describe('Table Relationships', function () {

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

    it('shows outgoing foreign keys from posts table', function () {
        $relationships = $this->db->getTableRelationships('posts', 'testing');

        expect($relationships)->toHaveKey('references')
            ->and($relationships)->toHaveKey('referenced_by')
            ->and($relationships['references'])->toBeArray();

        // Posts should have FKs to users and categories
        $fkTables = array_map(fn($fk) => $fk['foreign_table'] ?? null, $relationships['references']);
        expect($fkTables)->toContain('users')
            ->and($fkTables)->toContain('categories');
    });

    it('shows incoming foreign keys to users table', function () {
        $relationships = $this->db->getTableRelationships('users', 'testing');

        expect($relationships)->toHaveKey('referenced_by')
            ->and($relationships['referenced_by'])->toBeArray();

        // Posts references users
        $referencingTables = array_map(fn($fk) => $fk['table'] ?? null, $relationships['referenced_by']);
        expect($referencingTables)->toContain('posts');
    });

    it('returns empty arrays for table with no foreign keys', function () {
        $relationships = $this->db->getTableRelationships('categories', 'testing');

        expect($relationships)->toHaveKey('references')
            ->and($relationships)->toHaveKey('referenced_by')
            ->and($relationships['references'])->toBeArray();

        // Categories has no outgoing FKs (though posts references it)
        expect($relationships['references'])->toBeEmpty();
    });

    it('returns valid table names in relationships', function () {
        $relationships = $this->db->getTableRelationships('posts', 'testing');
        $allTables = $this->db->getTables('testing');
        $tableNames = array_column($allTables, 'name');

        // All referenced tables should exist
        foreach ($relationships['references'] as $fk) {
            $foreignTable = $fk['foreign_table'] ?? null;
            if ($foreignTable) {
                expect($tableNames)->toContain($foreignTable);
            }
        }

        // All referencing tables should exist
        foreach ($relationships['referenced_by'] as $fk) {
            $table = $fk['table'] ?? null;
            if ($table) {
                expect($tableNames)->toContain($table);
            }
        }
    });

    it('includes foreign key details in relationships', function () {
        $relationships = $this->db->getTableRelationships('posts', 'testing');

        expect($relationships['references'])->not->toBeEmpty();

        $userFk = collect($relationships['references'])->firstWhere('foreign_table', 'users');
        expect($userFk)->not->toBeNull()
            ->and($userFk)->toHaveKey('columns')
            ->and($userFk)->toHaveKey('foreign_columns');
    });
});
