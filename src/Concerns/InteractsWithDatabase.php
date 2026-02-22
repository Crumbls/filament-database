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

    /**
     * Get relationships for a table (both outgoing and incoming foreign keys).
     * 
     * @return array{references: array, referenced_by: array}
     */
    public function getTableRelationships(string $table, ?string $connection = null): array
    {
        // Outgoing: This table's foreign keys (references TO other tables)
        $references = $this->getForeignKeys($table, $connection);

        // Incoming: Other tables that reference this table
        $referencedBy = [];
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
        $className = 'Migrate' . str_replace(' ', '', ucwords(str_replace('_', ' ', $migrationName)));
        $code = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\n";
        $code .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $code .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $code .= "return new class extends Migration\n{\n";
        $code .= "    public function up(): void\n    {\n";

        // Create new tables
        foreach ($diff['tables_added'] as $table) {
            $code .= "        // TODO: Define schema for new table '{$table}'\n";
            $code .= "        // Schema::create('{$table}', function (Blueprint \$table) {\n";
            $code .= "        //     \$table->id();\n";
            $code .= "        //     \$table->timestamps();\n";
            $code .= "        // });\n\n";
        }

        // Modify existing tables
        foreach ($diff['tables_modified'] as $table => $changes) {
            $hasChanges = false;
            $tableCode = "        Schema::table('{$table}', function (Blueprint \$table) {\n";

            // Add columns
            foreach ($changes['columns_added'] as $col) {
                $hasChanges = true;
                $tableCode .= $this->generateColumnDefinition($col, '            ');
            }

            // Drop columns
            foreach ($changes['columns_removed'] as $col) {
                $hasChanges = true;
                $tableCode .= "            \$table->dropColumn('{$col['name']}');\n";
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
                if ($idx['primary'] ?? false) {
                    $tableCode .= "            \$table->dropPrimary('{$idx['name']}');\n";
                } elseif ($idx['unique'] ?? false) {
                    $tableCode .= "            \$table->dropUnique('{$idx['name']}');\n";
                } else {
                    $tableCode .= "            \$table->dropIndex('{$idx['name']}');\n";
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
                if (!empty($fk['name'])) {
                    $tableCode .= "            \$table->dropForeign('{$fk['name']}');\n";
                }
            }

            $tableCode .= "        });\n\n";

            if ($hasChanges) {
                $code .= $tableCode;
            }
        }

        // Drop tables
        foreach ($diff['tables_removed'] as $table) {
            $code .= "        Schema::dropIfExists('{$table}');\n";
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
        $name = $column['name'];

        $line = "{$indent}\$table->{$type}('{$name}')";

        if ($column['nullable'] ?? false) {
            $line .= "->nullable()";
        }

        if (isset($column['default']) && $column['default'] !== null) {
            $default = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
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
        $columns = $index['columns'] ?? [];
        $columnsStr = "'" . implode("', '", $columns) . "'";

        if ($index['primary'] ?? false) {
            return "{$indent}\$table->primary([{$columnsStr}]);\n";
        } elseif ($index['unique'] ?? false) {
            return "{$indent}\$table->unique([{$columnsStr}]);\n";
        } else {
            return "{$indent}\$table->index([{$columnsStr}]);\n";
        }
    }

    /**
     * Generate foreign key definition code.
     */
    protected function generateForeignKeyDefinition(array $fk, string $indent = ''): string
    {
        $columns = $fk['columns'] ?? [];
        $foreignTable = $fk['foreign_table'] ?? '';
        $foreignColumns = $fk['foreign_columns'] ?? [];

        $columnsStr = "'" . implode("', '", $columns) . "'";
        $foreignColumnsStr = "'" . implode("', '", $foreignColumns) . "'";

        $line = "{$indent}\$table->foreign([{$columnsStr}])->references([{$foreignColumnsStr}])->on('{$foreignTable}')";

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
