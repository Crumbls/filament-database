<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Crumbls\FilamentDatabase\Support\ExplainQuery;

describe('SQL EXPLAIN functionality', function () {
    beforeEach(function () {
        $this->seedTestData();

        $this->explainer = new class {
            use InteractsWithDatabase;
        };
    });

    it('builds the driver-specific statement and output format', function (
        string $driver,
        string $type,
        string $expectedSql,
        string $expectedFormat,
    ) {
        $query = ExplainQuery::build($driver, 'SELECT * FROM users', $type);

        expect($query->sql)->toBe($expectedSql)
            ->and($query->format)->toBe($expectedFormat);
    })->with([
        'MySQL EXPLAIN' => ['mysql', 'explain', 'EXPLAIN SELECT * FROM users', 'table'],
        'MySQL ANALYZE' => ['mysql', 'analyze', 'EXPLAIN ANALYZE SELECT * FROM users', 'text'],
        'MariaDB EXPLAIN' => ['mariadb', 'explain', 'EXPLAIN SELECT * FROM users', 'table'],
        'PostgreSQL EXPLAIN' => ['pgsql', 'explain', 'EXPLAIN SELECT * FROM users', 'text'],
        'PostgreSQL ANALYZE' => ['pgsql', 'analyze', 'EXPLAIN ANALYZE SELECT * FROM users', 'text'],
        'SQLite EXPLAIN' => ['sqlite', 'explain', 'EXPLAIN QUERY PLAN SELECT * FROM users', 'table'],
        'SQLite ANALYZE fallback' => ['sqlite', 'analyze', 'EXPLAIN QUERY PLAN SELECT * FROM users', 'table'],
    ]);

    it('executes and formats a SQLite query plan', function () {
        $query = ExplainQuery::build(
            'sqlite',
            'SELECT users.name FROM users JOIN posts ON users.id = posts.user_id',
        );
        $results = $this->explainer->runQuery($query->sql, 'testing');
        $formatted = $query->formatResults($results);

        expect($formatted)->not->toBeEmpty()
            ->and($formatted[0])->toBeArray()
            ->and($formatted[0])->toHaveKeys(['id', 'parent', 'detail']);
    });

    it('normalizes text plans without leaking driver-specific row shapes', function () {
        $postgres = ExplainQuery::build('pgsql', 'SELECT * FROM users');
        $mysql = ExplainQuery::build('mysql', 'SELECT * FROM users', 'analyze');

        expect($postgres->formatResults([
            (object) ['QUERY PLAN' => 'Index Scan'],
            (object) ['QUERY PLAN' => 'Filter'],
        ]))->toBe(['Index Scan', 'Filter'])
            ->and($mysql->formatResults([
                (object) ['EXPLAIN' => 'Nested loop'],
            ]))->toBe(['Nested loop']);
    });

    it('rejects unsupported statements, modes, and drivers', function (
        string $driver,
        string $sql,
        string $type,
    ) {
        expect(fn () => ExplainQuery::build($driver, $sql, $type))
            ->toThrow(InvalidArgumentException::class);
    })->with([
        'write statement' => ['sqlite', 'DELETE FROM users', 'explain'],
        'unknown mode' => ['sqlite', 'SELECT * FROM users', 'profile'],
        'unsupported driver' => ['mongodb', 'SELECT * FROM users', 'explain'],
    ]);
});
