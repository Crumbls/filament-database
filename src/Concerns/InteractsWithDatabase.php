<?php

namespace Crumbls\FilamentDatabase\Concerns;

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InteractsWithDatabase
{
    public function getAvailableConnections(): array
    {
        $all = array_keys(config('database.connections', []));

        $allowed = FilamentDatabasePlugin::get()->getAllowedConnections();

        if ($allowed !== null) {
            return array_intersect($all, $allowed);
        }

        return $all;
    }

    public function getSchemaBuilder(?string $connection = null): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection($connection);
    }

    public function getTables(?string $connection = null): array
    {
        return $this->getSchemaBuilder($connection)->getTables();
    }

    public function getColumns(string $table, ?string $connection = null): array
    {
        return $this->getSchemaBuilder($connection)->getColumns($table);
    }

    public function getIndexes(string $table, ?string $connection = null): array
    {
        return $this->getSchemaBuilder($connection)->getIndexes($table);
    }

    public function getForeignKeys(string $table, ?string $connection = null): array
    {
        return $this->getSchemaBuilder($connection)->getForeignKeys($table);
    }

    public function getDriverName(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName();
    }

    public function getRows(string $table, ?string $connection = null, int $page = 1, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::connection($connection)
            ->table($table)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function insertRow(string $table, array $data, ?string $connection = null): bool
    {
        return DB::connection($connection)->table($table)->insert($data);
    }

    public function updateRow(string $table, array $where, array $data, ?string $connection = null): int
    {
        $query = DB::connection($connection)->table($table);
        foreach ($where as $col => $val) {
            $query->where($col, $val);
        }
        return $query->update($data);
    }

    public function deleteRow(string $table, array $where, ?string $connection = null): int
    {
        $query = DB::connection($connection)->table($table);
        foreach ($where as $col => $val) {
            $query->where($col, $val);
        }
        return $query->delete();
    }

    public function runQuery(string $sql, ?string $connection = null): array
    {
        $sql = trim($sql);
        $upper = strtoupper(substr($sql, 0, 6));

        if (in_array($upper, ['SELECT', 'SHOW  ', 'DESCRI', 'EXPLAI'])) {
            return DB::connection($connection)->select($sql);
        }

        return [['affected_rows' => DB::connection($connection)->statement($sql)]];
    }

    public function dropTable(string $table, ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->drop($table);
    }

    public function truncateTable(string $table, ?string $connection = null): void
    {
        DB::connection($connection)->table($table)->truncate();
    }

    public function renameTable(string $from, string $to, ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->rename($from, $to);
    }

    public function dropColumn(string $table, string $column, ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->dropColumns($table, [$column]);
    }

    public function addColumn(string $table, string $name, string $type, array $options = [], ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->table($table, function ($blueprint) use ($name, $type, $options) {
            $col = $blueprint->{$type}($name, ...($options['arguments'] ?? []));

            if ($options['nullable'] ?? false) {
                $col->nullable();
            }
            if (array_key_exists('default', $options)) {
                $col->default($options['default']);
            }
            if ($options['after'] ?? null) {
                $col->after($options['after']);
            }
        });
    }

    public function renameColumn(string $table, string $from, string $to, ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->table($table, function ($blueprint) use ($from, $to) {
            $blueprint->renameColumn($from, $to);
        });
    }

    public function modifyColumn(string $table, string $name, string $type, array $options, ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->table($table, function ($blueprint) use ($name, $type, $options) {
            $col = $blueprint->{$type}($name, ...($options['arguments'] ?? []))->change();

            if ($options['nullable'] ?? false) {
                $col->nullable();
            } else {
                $col->nullable(false);
            }

            if (array_key_exists('default', $options) && $options['default'] !== null && $options['default'] !== '') {
                $col->default($options['default']);
            }
        });
    }

    public function createTable(string $name, array $columns, ?string $connection = null): void
    {
        $this->getSchemaBuilder($connection)->create($name, function ($blueprint) use ($columns) {
            foreach ($columns as $col) {
                $column = $blueprint->{$col['type']}($col['name'], ...($col['arguments'] ?? []));
                if ($col['nullable'] ?? false) {
                    $column->nullable();
                }
                if (array_key_exists('default', $col)) {
                    $column->default($col['default']);
                }
                if ($col['primary'] ?? false) {
                    $column->primary();
                }
                if ($col['autoIncrement'] ?? false) {
                    $column->autoIncrement();
                }
            }
        });
    }
}
