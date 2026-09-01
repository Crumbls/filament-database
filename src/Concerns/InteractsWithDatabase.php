<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Concerns;

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InteractsWithDatabase
{
    private const MAX_SQL_RESULT_ROWS = 500;

    private const MAX_SCHEMA_COLUMNS = 100;

    private const MAX_SCHEMA_IDENTIFIER_LENGTH = 63;

    protected const ALLOWED_COLUMN_TYPES = [
        'id', 'bigIncrements', 'bigInteger', 'binary', 'boolean',
        'char', 'date', 'dateTime', 'decimal', 'double',
        'enum', 'float', 'increments', 'integer', 'json',
        'jsonb', 'longText', 'mediumInteger', 'mediumText',
        'smallInteger', 'string', 'text', 'time', 'timestamp',
        'tinyInteger', 'unsignedBigInteger', 'unsignedInteger', 'uuid',
    ];

    protected array $databaseMetadataCache = [
        'tables' => [],
        'columns' => [],
        'indexes' => [],
        'foreign_keys' => [],
    ];

    protected function canAccessDatabaseTable(string $table, ?string $connection): bool
    {
        return true;
    }

    protected function authorizeDatabaseConnection(?string $connection): void
    {
        // Components may override this hook to enforce their access boundary.
    }

    protected function authorizeDatabaseTable(
        string $table,
        ?string $connection,
        bool $mustExist = true,
    ): void {
        $this->authorizeDatabaseConnection($connection);
    }

    protected function authorizeSqlQuery(string $sql, ?string $connection): void
    {
        $this->authorizeDatabaseConnection($connection);
    }

    protected function authorizeRowMutation(
        string $table,
        array $where,
        ?string $connection,
        bool $destructive,
    ): void {
        $this->authorizeDatabaseTable($table, $connection);
    }

    protected function isReadOnlySql(string $sql): bool
    {
        $sql = trim($sql);

        if (
            $sql === ''
            || preg_match('/;\s*\S/s', $sql) === 1
            || preg_match(
                '/\b(?:INTO|FOR\s+(?:UPDATE|NO\s+KEY\s+UPDATE|SHARE|KEY\s+SHARE)|LOCK\s+IN\s+SHARE\s+MODE)\b/i',
                $sql,
            ) === 1
        ) {
            return false;
        }

        return match (true) {
            preg_match('/\A(?:SELECT|SHOW|DESCRIBE)\b/is', $sql) === 1 => true,
            preg_match(
                '/\AEXPLAIN(?:\s+(?:QUERY\s+PLAN|ANALYZE))?\s+SELECT\b/is',
                $sql,
            ) === 1 => true,
            default => false,
        };
    }

    protected function sqlReturnsRows(string $sql): bool
    {
        return preg_match('/\A(?:SELECT|SHOW|DESCRIBE|EXPLAIN)\b/is', trim($sql)) === 1;
    }

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
        $this->authorizeDatabaseConnection($connection);

        return Schema::connection($connection);
    }

    public function getTables(?string $connection = null): array
    {
        $key = $this->databaseMetadataCacheKey($connection);

        if (array_key_exists($key, $this->databaseMetadataCache['tables'])) {
            return $this->databaseMetadataCache['tables'][$key];
        }

        return $this->databaseMetadataCache['tables'][$key] = array_values(array_filter(
            $this->getSchemaBuilder($connection)->getTables(),
            fn (array $table): bool => $this->canAccessDatabaseTable(
                $table['name'],
                $connection,
            ),
        ));
    }

    public function getColumns(string $table, ?string $connection = null): array
    {
        $this->authorizeDatabaseTable($table, $connection);

        $key = $this->databaseMetadataCacheKey($connection, $table);

        if (array_key_exists($key, $this->databaseMetadataCache['columns'])) {
            return $this->databaseMetadataCache['columns'][$key];
        }

        return $this->databaseMetadataCache['columns'][$key] = $this
            ->getSchemaBuilder($connection)
            ->getColumns($table);
    }

    public function getIndexes(string $table, ?string $connection = null): array
    {
        $this->authorizeDatabaseTable($table, $connection);

        $key = $this->databaseMetadataCacheKey($connection, $table);

        if (array_key_exists($key, $this->databaseMetadataCache['indexes'])) {
            return $this->databaseMetadataCache['indexes'][$key];
        }

        return $this->databaseMetadataCache['indexes'][$key] = $this
            ->getSchemaBuilder($connection)
            ->getIndexes($table);
    }

    public function getForeignKeys(string $table, ?string $connection = null): array
    {
        $this->authorizeDatabaseTable($table, $connection);

        $key = $this->databaseMetadataCacheKey($connection, $table);

        if (array_key_exists($key, $this->databaseMetadataCache['foreign_keys'])) {
            return $this->databaseMetadataCache['foreign_keys'][$key];
        }

        return $this->databaseMetadataCache['foreign_keys'][$key] = array_values(array_filter(
            $this->getSchemaBuilder($connection)->getForeignKeys($table),
            fn (array $foreignKey): bool => ! isset($foreignKey['foreign_table'])
                || $this->canAccessDatabaseTable($foreignKey['foreign_table'], $connection),
        ));
    }

    public function getDriverName(?string $connection = null): string
    {
        $this->authorizeDatabaseConnection($connection);

        return DB::connection($connection)->getDriverName();
    }

    public function getRows(string $table, ?string $connection = null, int $page = 1, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $this->authorizeDatabaseTable($table, $connection);

        return DB::connection($connection)
            ->table($table)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function insertRow(string $table, array $data, ?string $connection = null): bool
    {
        $this->authorizeDatabaseTable($table, $connection);

        return DB::connection($connection)->table($table)->insert($data);
    }

    public function updateRow(string $table, array $where, array $data, ?string $connection = null): int
    {
        $this->authorizeRowMutation($table, $where, $connection, destructive: false);

        $query = DB::connection($connection)->table($table);
        foreach ($where as $col => $val) {
            $query->where($col, $val);
        }
        return $query->update($data);
    }

    public function deleteRow(string $table, array $where, ?string $connection = null): int
    {
        $this->authorizeRowMutation($table, $where, $connection, destructive: true);

        $query = DB::connection($connection)->table($table);
        foreach ($where as $col => $val) {
            $query->where($col, $val);
        }
        return $query->delete();
    }

    public function runQuery(string $sql, ?string $connection = null): array
    {
        $this->authorizeSqlQuery($sql, $connection);

        $sql = trim($sql);

        if ($this->sqlReturnsRows($sql)) {
            $rows = [];

            foreach (DB::connection($connection)->cursor($sql) as $row) {
                $rows[] = $row;

                if (count($rows) >= self::MAX_SQL_RESULT_ROWS) {
                    break;
                }
            }

            return $rows;
        }

        return [['affected_rows' => DB::connection($connection)->statement($sql)]];
    }

    public function dropTable(string $table, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($table, $connection);

        $this->getSchemaBuilder($connection)->drop($table);
        $this->flushDatabaseMetadataCache($connection);
    }

    public function truncateTable(string $table, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($table, $connection);

        DB::connection($connection)->table($table)->truncate();
    }

    public function renameTable(string $from, string $to, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($from, $connection);
        $this->authorizeDatabaseTable($to, $connection, mustExist: false);

        $this->assertValidSchemaIdentifier($to, 'table');

        if ($this->getSchemaBuilder($connection)->hasTable($to)) {
            throw new \InvalidArgumentException("Table already exists: {$to}");
        }

        $this->getSchemaBuilder($connection)->rename($from, $to);
        $this->flushDatabaseMetadataCache($connection);
    }

    public function dropColumn(string $table, string $column, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($table, $connection);

        $this->assertColumnExists($table, $column, $connection);

        $this->getSchemaBuilder($connection)->dropColumns($table, [$column]);
        $this->flushDatabaseMetadataCache($connection);
    }

    public function addColumn(string $table, string $name, string $type, array $options = [], ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($table, $connection);

        $this->assertValidSchemaIdentifier($name, 'column');

        if ($this->getSchemaBuilder($connection)->hasColumn($table, $name)) {
            throw new \InvalidArgumentException("Column already exists: {$name}");
        }

        $this->assertValidColumnDefinition([
            ...$options,
            'name' => $name,
            'type' => $type,
        ]);

        if (isset($options['after'])) {
            if (! is_string($options['after'])) {
                throw new \InvalidArgumentException('The after column option must be a string.');
            }

            $this->assertColumnExists($table, $options['after'], $connection);
        }

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
        $this->flushDatabaseMetadataCache($connection);
    }

    public function renameColumn(string $table, string $from, string $to, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($table, $connection);

        $this->assertColumnExists($table, $from, $connection);
        $this->assertValidSchemaIdentifier($to, 'column');

        if ($from !== $to && $this->getSchemaBuilder($connection)->hasColumn($table, $to)) {
            throw new \InvalidArgumentException("Column already exists: {$to}");
        }

        $this->getSchemaBuilder($connection)->table($table, function ($blueprint) use ($from, $to) {
            $blueprint->renameColumn($from, $to);
        });
        $this->flushDatabaseMetadataCache($connection);
    }

    public function modifyColumn(string $table, string $name, string $type, array $options, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($table, $connection);

        $this->assertColumnExists($table, $name, $connection);

        $this->assertValidColumnDefinition([
            ...$options,
            'name' => $name,
            'type' => $type,
        ]);

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
        $this->flushDatabaseMetadataCache($connection);
    }

    public function createTable(string $name, array $columns, ?string $connection = null): void
    {
        $this->authorizeDatabaseTable($name, $connection, mustExist: false);

        $this->assertValidSchemaIdentifier($name, 'table');

        if ($this->getSchemaBuilder($connection)->hasTable($name)) {
            throw new \InvalidArgumentException("Table already exists: {$name}");
        }

        if ($columns === [] || count($columns) > self::MAX_SCHEMA_COLUMNS) {
            throw new \InvalidArgumentException(sprintf(
                'A table must contain between 1 and %d columns.',
                self::MAX_SCHEMA_COLUMNS,
            ));
        }

        $columnNames = [];

        foreach ($columns as $column) {
            if (! is_array($column)) {
                throw new \InvalidArgumentException('Each column definition must be an array.');
            }

            $this->assertValidColumnDefinition($column);

            $normalizedName = strtolower($column['name']);

            if (isset($columnNames[$normalizedName])) {
                throw new \InvalidArgumentException("Duplicate column name: {$column['name']}");
            }

            $columnNames[$normalizedName] = true;
        }

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
        $this->flushDatabaseMetadataCache($connection);
    }

    protected function databaseMetadataCacheKey(?string $connection, ?string $table = null): string
    {
        return implode(':', [
            $connection ?? (string) config('database.default'),
            $table ?? '',
        ]);
    }

    protected function flushDatabaseMetadataCache(?string $connection = null): void
    {
        if ($connection === null) {
            $this->databaseMetadataCache = [
                'tables' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
            ];

            return;
        }

        $prefix = $this->databaseMetadataCacheKey($connection);

        foreach ($this->databaseMetadataCache as &$entries) {
            foreach (array_keys($entries) as $key) {
                if (str_starts_with($key, $prefix)) {
                    unset($entries[$key]);
                }
            }
        }

        unset($entries);
    }

    protected function assertValidSchemaIdentifier(string $identifier, string $kind): void
    {
        if (
            $identifier === ''
            || strlen($identifier) > self::MAX_SCHEMA_IDENTIFIER_LENGTH
            || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $identifier) !== 1
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid %s name: identifiers must start with a letter or underscore, contain only letters, numbers, and underscores, and be at most %d characters.',
                $kind,
                self::MAX_SCHEMA_IDENTIFIER_LENGTH,
            ));
        }
    }

    protected function assertValidColumnDefinition(array $column): void
    {
        if (! isset($column['name'], $column['type']) || ! is_string($column['name']) || ! is_string($column['type'])) {
            throw new \InvalidArgumentException('Column definitions require string name and type values.');
        }

        $this->assertValidSchemaIdentifier($column['name'], 'column');

        if (! in_array($column['type'], self::ALLOWED_COLUMN_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid column type: {$column['type']}");
        }

        foreach (['nullable', 'primary', 'autoIncrement'] as $booleanOption) {
            if (array_key_exists($booleanOption, $column) && ! is_bool($column[$booleanOption])) {
                throw new \InvalidArgumentException("Column option {$booleanOption} must be boolean.");
            }
        }

        if (
            array_key_exists('default', $column)
            && ! is_scalar($column['default'])
            && $column['default'] !== null
        ) {
            throw new \InvalidArgumentException('Column defaults must be scalar values or null.');
        }

        $arguments = $column['arguments'] ?? [];

        if (! is_array($arguments) || count($arguments) > 3) {
            throw new \InvalidArgumentException('Column arguments must be an array containing at most three values.');
        }

        $this->assertValidColumnArguments($column['type'], $arguments);
    }

    protected function assertValidColumnArguments(string $type, array $arguments): void
    {
        if ($arguments === []) {
            return;
        }

        if (in_array($type, ['char', 'string'], true)) {
            if (count($arguments) !== 1 || ! is_int($arguments[0]) || $arguments[0] < 1 || $arguments[0] > 65535) {
                throw new \InvalidArgumentException('String column length must be an integer between 1 and 65535.');
            }

            return;
        }

        if ($type === 'decimal') {
            if (
                count($arguments) > 2
                || array_filter($arguments, static fn (mixed $value): bool => ! is_int($value)) !== []
                || $arguments[0] < 1
                || $arguments[0] > 65
                || (isset($arguments[1]) && ($arguments[1] < 0 || $arguments[1] > $arguments[0]))
            ) {
                throw new \InvalidArgumentException('Decimal precision and scale are invalid.');
            }

            return;
        }

        if ($type === 'enum') {
            $values = $arguments[0] ?? null;

            if (
                count($arguments) !== 1
                || ! is_array($values)
                || $values === []
                || count($values) > 100
                || array_filter(
                    $values,
                    static fn (mixed $value): bool => ! is_string($value) || $value === '' || strlen($value) > 255,
                ) !== []
            ) {
                throw new \InvalidArgumentException('Enum columns require between 1 and 100 non-empty string values.');
            }

            return;
        }

        throw new \InvalidArgumentException("Column type {$type} does not accept arguments.");
    }

    protected function assertColumnExists(string $table, string $column, ?string $connection): void
    {
        if (! $this->getSchemaBuilder($connection)->hasColumn($table, $column)) {
            throw new \InvalidArgumentException("Column does not exist: {$column}");
        }
    }

    /**
     * Get relationships for a table (both outgoing and incoming foreign keys).
     * 
     * @return array{references: array, referenced_by: array}
     */
    public function getTableRelationships(string $table, ?string $connection = null): array
    {
        $this->authorizeDatabaseTable($table, $connection);

        // Outgoing: This table's foreign keys (references TO other tables)
        $references = $this->getForeignKeys($table, $connection);

        // Incoming: Other tables that reference this table
        $referencedBy = [];
        $driver = $this->getDriverName($connection);

        if ($driver === 'mysql') {
            $database = DB::connection($connection)->getDatabaseName();
            $rows = DB::connection($connection)
                ->table('information_schema.KEY_COLUMN_USAGE')
                ->select(['TABLE_NAME', 'COLUMN_NAME', 'CONSTRAINT_NAME', 'REFERENCED_COLUMN_NAME'])
                ->where('CONSTRAINT_SCHEMA', $database)
                ->where('REFERENCED_TABLE_SCHEMA', $database)
                ->where('REFERENCED_TABLE_NAME', $table)
                ->get();

            foreach ($rows as $row) {
                if (! $this->canAccessDatabaseTable($row->TABLE_NAME, $connection)) {
                    continue;
                }

                $referencedBy[] = [
                    'table'           => $row->TABLE_NAME,
                    'columns'         => [$row->COLUMN_NAME],
                    'foreign_table'   => $table,
                    'foreign_columns' => [$row->REFERENCED_COLUMN_NAME],
                    'name'            => $row->CONSTRAINT_NAME,
                ];
            }
        } else {
            $allTables = $this->getTables($connection);

            foreach ($allTables as $tableInfo) {
                $otherTable = $tableInfo['name'];
                if ($otherTable === $table) {
                    continue;
                }

                $foreignKeys = $this->getForeignKeys($otherTable, $connection);
                foreach ($foreignKeys as $fk) {
                    if (($fk['foreign_table'] ?? null) === $table) {
                        $referencedBy[] = array_merge($fk, ['table' => $otherTable]);
                    }
                }
            }
        }

        return [
            'references' => $references,
            'referenced_by' => $referencedBy,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Schema Snapshots & Migration Generator
    // ═══════════════════════════════════════════════════════════

    /**
     * Capture the entire database schema as JSON.
     */
    public function captureSchema(?string $connection = null): array
    {
        $this->flushDatabaseMetadataCache($connection);

        $schema = [];
        $tables = $this->getTables($connection);

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $columns = $this->getColumns($tableName, $connection);
            $indexes = $this->getIndexes($tableName, $connection);
            $foreignKeys = $this->getForeignKeys($tableName, $connection);

            $schema[$tableName] = [
                'columns' => array_map(function ($col) {
                    return [
                        'name' => $col['name'],
                        'type' => $col['type_name'] ?? $col['type'],
                        'nullable' => $col['nullable'] ?? false,
                        'default' => $col['default'] ?? null,
                        'auto_increment' => $col['auto_increment'] ?? false,
                    ];
                }, $columns),
                'indexes' => array_map(function ($idx) {
                    return [
                        'name' => $idx['name'],
                        'columns' => $idx['columns'] ?? [],
                        'type' => $idx['type'] ?? 'index',
                        'unique' => $idx['unique'] ?? false,
                        'primary' => $idx['primary'] ?? false,
                    ];
                }, $indexes),
                'foreign_keys' => array_map(function ($fk) {
                    return [
                        'name' => $fk['name'] ?? '',
                        'columns' => $fk['columns'] ?? [],
                        'foreign_table' => $fk['foreign_table'] ?? '',
                        'foreign_columns' => $fk['foreign_columns'] ?? [],
                        'on_update' => $fk['on_update'] ?? null,
                        'on_delete' => $fk['on_delete'] ?? null,
                    ];
                }, $foreignKeys),
            ];
        }

        return $schema;
    }

    /**
     * Compare two schemas and return a diff.
     */
    public function compareSchemas(array $oldSchema, array $newSchema): array
    {
        $diff = [
            'tables_added' => [],
            'tables_removed' => [],
            'tables_modified' => [],
        ];

        $oldTables = array_keys($oldSchema);
        $newTables = array_keys($newSchema);

        // Tables added
        $diff['tables_added'] = array_diff($newTables, $oldTables);

        // Tables removed
        $diff['tables_removed'] = array_diff($oldTables, $newTables);

        // Tables modified
        $commonTables = array_intersect($oldTables, $newTables);
        foreach ($commonTables as $table) {
            $tableDiff = $this->compareTableSchema($oldSchema[$table], $newSchema[$table]);
            if (!empty($tableDiff['columns_added']) || !empty($tableDiff['columns_removed']) ||
                !empty($tableDiff['columns_modified']) || !empty($tableDiff['indexes_added']) ||
                !empty($tableDiff['indexes_removed']) || !empty($tableDiff['foreign_keys_added']) ||
                !empty($tableDiff['foreign_keys_removed'])) {
                $diff['tables_modified'][$table] = $tableDiff;
            }
        }

        return $diff;
    }

    /**
     * Compare individual table schemas.
     */
    protected function compareTableSchema(array $oldTable, array $newTable): array
    {
        $diff = [
            'columns_added' => [],
            'columns_removed' => [],
            'columns_modified' => [],
            'indexes_added' => [],
            'indexes_removed' => [],
            'foreign_keys_added' => [],
            'foreign_keys_removed' => [],
        ];

        // Columns
        $oldColumns = collect($oldTable['columns'])->keyBy('name');
        $newColumns = collect($newTable['columns'])->keyBy('name');

        $diff['columns_added'] = $newColumns->diffKeys($oldColumns)->values()->toArray();
        $diff['columns_removed'] = $oldColumns->diffKeys($newColumns)->values()->toArray();

        // Modified columns
        foreach ($newColumns as $name => $newCol) {
            if ($oldColumns->has($name)) {
                $oldCol = $oldColumns->get($name);
                if (
                    $oldCol['type'] !== $newCol['type'] ||
                    $oldCol['nullable'] !== $newCol['nullable'] ||
                    $oldCol['default'] !== $newCol['default']
                ) {
                    $diff['columns_modified'][] = [
                        'name' => $name,
                        'old' => $oldCol,
                        'new' => $newCol,
                    ];
                }
            }
        }

        // Indexes
        $oldIndexes = collect($oldTable['indexes'])->map(fn($idx) => $idx['name'])->filter()->values();
        $newIndexes = collect($newTable['indexes'])->map(fn($idx) => $idx['name'])->filter()->values();

        $diff['indexes_added'] = collect($newTable['indexes'])
            ->filter(fn($idx) => !$oldIndexes->contains($idx['name']))
            ->values()
            ->toArray();

        $diff['indexes_removed'] = collect($oldTable['indexes'])
            ->filter(fn($idx) => !$newIndexes->contains($idx['name']))
            ->values()
            ->toArray();

        // Foreign keys
        $oldFks = collect($oldTable['foreign_keys'])->map(fn($fk) => $fk['name'])->filter()->values();
        $newFks = collect($newTable['foreign_keys'])->map(fn($fk) => $fk['name'])->filter()->values();

        $diff['foreign_keys_added'] = collect($newTable['foreign_keys'])
            ->filter(fn($fk) => !$oldFks->contains($fk['name']))
            ->values()
            ->toArray();

        $diff['foreign_keys_removed'] = collect($oldTable['foreign_keys'])
            ->filter(fn($fk) => !$newFks->contains($fk['name']))
            ->values()
            ->toArray();

        return $diff;
    }

    /**
     * Generate Laravel migration code from a schema diff.
     */
    public function generateMigration(array $diff, string $migrationName = 'schema_changes'): string
    {
        $code = "<?php\n\ndeclare(strict_types=1);\n\n";
        $code .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $code .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $code .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $code .= "return new class extends Migration\n{\n";
        $code .= "    public function up(): void\n    {\n";

        // Create new tables
        foreach ($diff['tables_added'] as $table) {
            $tableLiteral = $this->phpLiteral((string) $table);
            $commentLiteral = str_replace(["\r", "\n"], ['\\r', '\\n'], $tableLiteral);

            $code .= "        // TODO: Define schema for new table {$commentLiteral}\n";
            $code .= "        // Schema::create({$commentLiteral}, function (Blueprint \$table) {\n";
            $code .= "        //     \$table->id();\n";
            $code .= "        //     \$table->timestamps();\n";
            $code .= "        // });\n\n";
        }

        // Modify existing tables
        foreach ($diff['tables_modified'] as $table => $changes) {
            $hasChanges = false;
            $tableLiteral = $this->phpLiteral((string) $table);
            $tableCode = "        Schema::table({$tableLiteral}, function (Blueprint \$table) {\n";

            // Add columns
            foreach ($changes['columns_added'] as $col) {
                $hasChanges = true;
                $tableCode .= $this->generateColumnDefinition($col, '            ');
            }

            // Drop columns
            foreach ($changes['columns_removed'] as $col) {
                $hasChanges = true;
                $columnLiteral = $this->phpLiteral((string) $col['name']);
                $tableCode .= "            \$table->dropColumn({$columnLiteral});\n";
            }

            // Modify columns
            foreach ($changes['columns_modified'] as $mod) {
                $hasChanges = true;
                $tableCode .= $this->generateColumnDefinition($mod['new'], '            ', true);
            }

            // Add indexes
            foreach ($changes['indexes_added'] as $idx) {
                $hasChanges = true;
                $tableCode .= $this->generateIndexDefinition($idx, '            ');
            }

            // Drop indexes
            foreach ($changes['indexes_removed'] as $idx) {
                $hasChanges = true;
                $indexLiteral = $this->phpLiteral((string) $idx['name']);

                if ($idx['primary'] ?? false) {
                    $tableCode .= "            \$table->dropPrimary({$indexLiteral});\n";
                } elseif ($idx['unique'] ?? false) {
                    $tableCode .= "            \$table->dropUnique({$indexLiteral});\n";
                } else {
                    $tableCode .= "            \$table->dropIndex({$indexLiteral});\n";
                }
            }

            // Add foreign keys
            foreach ($changes['foreign_keys_added'] as $fk) {
                $hasChanges = true;
                $tableCode .= $this->generateForeignKeyDefinition($fk, '            ');
            }

            // Drop foreign keys
            foreach ($changes['foreign_keys_removed'] as $fk) {
                $hasChanges = true;
                if (! empty($fk['name'])) {
                    $foreignKeyLiteral = $this->phpLiteral((string) $fk['name']);
                    $tableCode .= "            \$table->dropForeign({$foreignKeyLiteral});\n";
                }
            }

            $tableCode .= "        });\n\n";

            if ($hasChanges) {
                $code .= $tableCode;
            }
        }

        // Drop tables
        foreach ($diff['tables_removed'] as $table) {
            $tableLiteral = $this->phpLiteral((string) $table);
            $code .= "        Schema::dropIfExists({$tableLiteral});\n";
        }

        $code .= "    }\n\n";
        $code .= "    public function down(): void\n    {\n";
        $code .= "        // Reverse migration logic here\n";
        $code .= "    }\n";
        $code .= "};\n";

        return $code;
    }

    /**
     * Generate column definition code.
     */
    protected function generateColumnDefinition(array $column, string $indent = '', bool $isChange = false): string
    {
        $type = $this->mapDbTypeToMigrationType($column['type']);
        $name = $this->phpLiteral((string) $column['name']);

        $line = "{$indent}\$table->{$type}({$name})";

        if ($column['nullable'] ?? false) {
            $line .= "->nullable()";
        }

        if (isset($column['default']) && $column['default'] !== null) {
            $default = $this->phpLiteral($column['default']);
            $line .= "->default({$default})";
        }

        if ($isChange) {
            $line .= "->change()";
        }

        $line .= ";\n";

        return $line;
    }

    /**
     * Generate index definition code.
     */
    protected function generateIndexDefinition(array $index, string $indent = ''): string
    {
        $columns = array_map('strval', $index['columns'] ?? []);
        $columnsLiteral = $this->phpLiteral(array_values($columns));

        if ($index['primary'] ?? false) {
            return "{$indent}\$table->primary({$columnsLiteral});\n";
        } elseif ($index['unique'] ?? false) {
            return "{$indent}\$table->unique({$columnsLiteral});\n";
        } else {
            return "{$indent}\$table->index({$columnsLiteral});\n";
        }
    }

    /**
     * Generate foreign key definition code.
     */
    protected function generateForeignKeyDefinition(array $fk, string $indent = ''): string
    {
        $columns = array_map('strval', $fk['columns'] ?? []);
        $foreignTable = $this->phpLiteral((string) ($fk['foreign_table'] ?? ''));
        $foreignColumns = array_map('strval', $fk['foreign_columns'] ?? []);

        $columnsLiteral = $this->phpLiteral(array_values($columns));
        $foreignColumnsLiteral = $this->phpLiteral(array_values($foreignColumns));

        $line = "{$indent}\$table->foreign({$columnsLiteral})"
            . "->references({$foreignColumnsLiteral})->on({$foreignTable})";

        if (!empty($fk['on_delete'])) {
            $action = strtolower($fk['on_delete']);
            if ($action === 'cascade') {
                $line .= "->onDelete('cascade')";
            } elseif ($action === 'set null') {
                $line .= "->onDelete('set null')";
            } elseif ($action === 'restrict') {
                $line .= "->onDelete('restrict')";
            }
        }

        if (!empty($fk['on_update'])) {
            $action = strtolower($fk['on_update']);
            if ($action === 'cascade') {
                $line .= "->onUpdate('cascade')";
            } elseif ($action === 'set null') {
                $line .= "->onUpdate('set null')";
            } elseif ($action === 'restrict') {
                $line .= "->onUpdate('restrict')";
            }
        }

        $line .= ";\n";

        return $line;
    }

    protected function phpLiteral(mixed $value): string
    {
        return var_export($value, true);
    }

    /**
     * Map database type to Laravel migration type.
     */
    protected function mapDbTypeToMigrationType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match (true) {
            str_contains($dbType, 'int') && str_contains($dbType, 'big') => 'bigInteger',
            str_contains($dbType, 'int') && str_contains($dbType, 'tiny') => 'tinyInteger',
            str_contains($dbType, 'int') && str_contains($dbType, 'small') => 'smallInteger',
            str_contains($dbType, 'int') && str_contains($dbType, 'medium') => 'mediumInteger',
            str_contains($dbType, 'int') => 'integer',
            str_contains($dbType, 'varchar') => 'string',
            str_contains($dbType, 'char') => 'char',
            str_contains($dbType, 'text') && str_contains($dbType, 'long') => 'longText',
            str_contains($dbType, 'text') && str_contains($dbType, 'medium') => 'mediumText',
            str_contains($dbType, 'text') => 'text',
            str_contains($dbType, 'decimal') || str_contains($dbType, 'numeric') => 'decimal',
            str_contains($dbType, 'float') => 'float',
            str_contains($dbType, 'double') => 'double',
            str_contains($dbType, 'bool') || str_contains($dbType, 'tinyint(1)') => 'boolean',
            str_contains($dbType, 'date') && !str_contains($dbType, 'time') => 'date',
            str_contains($dbType, 'datetime') => 'dateTime',
            str_contains($dbType, 'timestamp') => 'timestamp',
            str_contains($dbType, 'time') => 'time',
            str_contains($dbType, 'json') => 'json',
            str_contains($dbType, 'binary') => 'binary',
            str_contains($dbType, 'uuid') => 'uuid',
            default => 'string',
        };
    }
}
