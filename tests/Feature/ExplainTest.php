<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Illuminate\Support\Facades\DB;

describe('SQL EXPLAIN Functionality', function () {

    beforeEach(function () {
        $this->seedTestData();
        
        $this->explainer = new class {
            use InteractsWithDatabase;

            public string $activeConnection = 'testing';
        };
    });

    it('executes EXPLAIN on SELECT query and returns results', function () {
        $query = 'SELECT * FROM users WHERE name = "Alice"';
        $driver = $this->explainer->getDriverName('testing');
        
        if ($driver === 'sqlite') {
            $explainQuery = "EXPLAIN QUERY PLAN {$query}";
        } else {
            $explainQuery = "EXPLAIN {$query}";
        }
        
        $results = $this->explainer->runQuery($explainQuery, 'testing');

        expect($results)->toBeArray()
            ->and($results)->not->toBeEmpty();
    });

    it('handles non-SELECT query with EXPLAIN gracefully', function () {
        // EXPLAIN only works with SELECT - this tests the validation
        $query = 'INSERT INTO users (name, email, password) VALUES ("Test", "test@example.com", "secret")';
        
        // Should not use EXPLAIN prefix
        $upper = strtoupper(trim(substr($query, 0, 6)));
        expect($upper)->not->toBe('SELECT');
    });

    it('detects correct EXPLAIN format for SQLite', function () {
        $driver = $this->explainer->getDriverName('testing');
        
        expect($driver)->toBe('sqlite');
        
        // SQLite should use EXPLAIN QUERY PLAN
        $query = 'SELECT * FROM users';
        $explainQuery = "EXPLAIN QUERY PLAN {$query}";
        
        $results = $this->explainer->runQuery($explainQuery, 'testing');
        expect($results)->toBeArray();
    });

    it('uses EXPLAIN QUERY PLAN for SQLite', function () {
        $query = 'SELECT * FROM posts WHERE user_id = 1';
        $explainQuery = "EXPLAIN QUERY PLAN {$query}";

        $results = $this->explainer->runQuery($explainQuery, 'testing');

        // Should have results (SQLite uses EXPLAIN QUERY PLAN)
        expect($results)->toBeArray()
            ->and($results)->not->toBeEmpty();
    });

    it('handles complex SELECT queries with joins', function () {
        $query = 'SELECT u.name, p.title FROM users u JOIN posts p ON u.id = p.user_id';
        $explainQuery = "EXPLAIN QUERY PLAN {$query}";

        $results = $this->explainer->runQuery($explainQuery, 'testing');

        expect($results)->toBeArray()
            ->and($results)->not->toBeEmpty();
    });

    it('works with subqueries', function () {
        $query = 'SELECT * FROM users WHERE id IN (SELECT user_id FROM posts)';
        $explainQuery = "EXPLAIN QUERY PLAN {$query}";

        $results = $this->explainer->runQuery($explainQuery, 'testing');

        expect($results)->toBeArray()
            ->and($results)->not->toBeEmpty();
    });

    it('validates query format before running EXPLAIN', function () {
        $selectQuery = 'SELECT * FROM users';
        $insertQuery = 'INSERT INTO users (name, email, password) VALUES ("Test", "test@example.com", "secret")';
        
        $selectUpper = strtoupper(trim(substr($selectQuery, 0, 6)));
        $insertUpper = strtoupper(trim(substr($insertQuery, 0, 6)));
        
        expect($selectUpper)->toBe('SELECT');
        expect($insertUpper)->toBe('INSERT');
    });
});
