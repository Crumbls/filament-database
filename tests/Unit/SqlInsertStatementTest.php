<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Support\SqlInsertStatement;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;

it('uses the active driver grammar and preserves value types', function (
    string $connectionClass,
    string $driver,
    string $expected,
) {
    $connection = new $connectionClass(new PDO('sqlite::memory:'), '', '', ['driver' => $driver]);
    $statement = (new SqlInsertStatement($connection))->build('audit-log', [
        'select' => "O'Reilly\\path",
        'code' => '00123',
        'enabled' => true,
        'attempts' => 7,
        'empty' => null,
    ]);

    expect($statement)->toBe($expected);
})->with([
    'SQLite' => [
        SQLiteConnection::class,
        'sqlite',
        'INSERT INTO "audit-log" ("select", "code", "enabled", "attempts", "empty") VALUES (\'O\'\'Reilly\\path\', \'00123\', 1, 7, NULL);',
    ],
    'MySQL' => [
        MySqlConnection::class,
        'mysql',
        'INSERT INTO `audit-log` (`select`, `code`, `enabled`, `attempts`, `empty`) VALUES (\'O\'\'Reilly\\path\', \'00123\', 1, 7, NULL);',
    ],
    'PostgreSQL' => [
        PostgresConnection::class,
        'pgsql',
        'INSERT INTO "audit-log" ("select", "code", "enabled", "attempts", "empty") VALUES (\'O\'\'Reilly\\path\', \'00123\', TRUE, 7, NULL);',
    ],
    'SQL Server' => [
        SqlServerConnection::class,
        'sqlsrv',
        'INSERT INTO [audit-log] ([select], [code], [enabled], [attempts], [empty]) VALUES (\'O\'\'Reilly\\path\', \'00123\', 1, 7, NULL);',
    ],
]);

it('rejects values that cannot be represented as SQL literals', function (mixed $value) {
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'));

    expect(fn () => (new SqlInsertStatement($connection))->build('users', ['payload' => $value]))
        ->toThrow(\InvalidArgumentException::class);
})->with([
    'array' => [['unsafe']],
    'non-finite float' => [NAN],
]);
