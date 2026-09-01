<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Repositories;

use Illuminate\Database\Connection;

final readonly class DatabaseMetadataRepository
{
    private const SQLITE_TABLES_PER_QUERY = 100;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int|null>
     */
    public function tableRowCounts(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        $counts = array_fill_keys($tables, null);
        $rows = match ($this->connection->getDriverName()) {
            'mysql', 'mariadb' => $this->mysqlRowCounts($tables),
            'pgsql' => $this->postgresRowCounts($tables),
            'sqlite' => $this->sqliteRowCounts($tables),
            'sqlsrv' => $this->sqlServerRowCounts($tables),
            default => [],
        };

        foreach ($rows as $row) {
            $row = (array) $row;
            $table = (string) ($row['table_name'] ?? '');

            if (array_key_exists($table, $counts)) {
                $counts[$table] = (int) ($row['row_count'] ?? 0);
            }
        }

        return $counts;
    }

    protected function mysqlRowCounts(array $tables): array
    {
        return $this->connection
            ->table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $this->connection->getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)
            ->get(['TABLE_NAME as table_name', 'TABLE_ROWS as row_count'])
            ->all();
    }

    protected function postgresRowCounts(array $tables): array
    {
        return $this->connection
            ->table('pg_stat_user_tables')
            ->whereRaw('schemaname = current_schema()')
            ->whereIn('relname', $tables)
            ->get(['relname as table_name', 'n_live_tup as row_count'])
            ->all();
    }

    protected function sqliteRowCounts(array $tables): array
    {
        $grammar = $this->connection->getQueryGrammar();
        $rows = [];

        foreach (array_chunk($tables, self::SQLITE_TABLES_PER_QUERY) as $chunk) {
            $statements = array_map(
                static fn (string $table): string => sprintf(
                    'select ? as table_name, count(*) as row_count from %s',
                    $grammar->wrapTable($table),
                ),
                $chunk,
            );

            $rows = [
                ...$rows,
                ...$this->connection->select(implode(' union all ', $statements), $chunk),
            ];
        }

        return $rows;
    }

    protected function sqlServerRowCounts(array $tables): array
    {
        return $this->connection
            ->table('sys.tables as tables')
            ->join('sys.partitions as partitions', 'tables.object_id', '=', 'partitions.object_id')
            ->whereIn('tables.name', $tables)
            ->whereIn('partitions.index_id', [0, 1])
            ->groupBy('tables.name')
            ->get([
                'tables.name as table_name',
                $this->connection->raw('SUM(partitions.rows) as row_count'),
            ])
            ->all();
    }
}
