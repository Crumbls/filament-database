<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Repositories\DatabaseMetadataRepository;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Facades\DB;

it('uses one aggregate metadata query for server database drivers', function (
    string $connectionClass,
    string $driver,
    string $expectedTable,
) {
    $connection = new $connectionClass(
        new PDO('sqlite::memory:'),
        'application',
        '',
        ['driver' => $driver],
    );
    $repository = new DatabaseMetadataRepository($connection);

    $queries = $connection->pretend(
        fn (): array => $repository->tableRowCounts(['users', 'posts']),
    );

    expect($queries)->toHaveCount(1)
        ->and(strtolower($queries[0]['query']))->toContain(strtolower($expectedTable));
})->with([
    'MySQL' => [MySqlConnection::class, 'mysql', 'information_schema'],
    'PostgreSQL' => [PostgresConnection::class, 'pgsql', 'pg_stat_user_tables'],
    'SQL Server' => [SqlServerConnection::class, 'sqlsrv', '[sys].[tables]'],
]);

it('does not query metadata when there are no visible tables', function () {
    $connection = DB::connection('testing');
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    $counts = (new DatabaseMetadataRepository($connection))->tableRowCounts([]);

    expect($counts)->toBe([])
        ->and($connection->getQueryLog())->toBe([]);
});
