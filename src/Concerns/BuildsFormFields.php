<?php

namespace Crumbls\FilamentDatabase\Concerns;

use Filament\Forms\Components;
use Filament\Forms\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait BuildsFormFields
{
    /**
     * Build Filament form fields from database column metadata.
     *
     * @param  array  $columns       Result of Schema::getColumns()
     * @param  array  $foreignKeys   Result of Schema::getForeignKeys()
     * @param  string $connection
     * @param  bool   $isInsert      Whether this is for insert (vs edit)
     * @return array<Components\Component>
     */
    protected function buildFormFields(array $columns, array $foreignKeys = [], string $connection = '', bool $isInsert = false): array
    {
        // Index foreign keys by column name for quick lookup
        $fkMap = [];
        foreach ($foreignKeys as $fk) {
            $fkColumns = $fk['columns'] ?? [];
            if (count($fkColumns) === 1) {
                $fkMap[$fkColumns[0]] = $fk;
            }
        }

        // Detect auto-increment columns
        $primaryKey = $this->detectPrimaryKey($this->activeTable, $connection);

        $fields = [];

        foreach ($columns as $col) {
            $name = $col['name'];
            $type = strtolower($col['type_name'] ?? $col['type'] ?? 'varchar');
            $nullable = $col['nullable'] ?? false;
            $default = $col['default'] ?? null;
            $autoIncrement = $col['auto_increment'] ?? false;

            // Skip auto-increment on insert
            if ($isInsert && $autoIncrement) {
                continue;
            }

            // Foreign key → Select with options from related table
            if (isset($fkMap[$name])) {
                $fk = $fkMap[$name];
                $foreignTable = $fk['foreign_table'] ?? null;
                $foreignCol = ($fk['foreign_columns'] ?? [])[0] ?? 'id';

                if ($foreignTable) {
                    $field = Components\Select::make($name)
                        ->label($name)
                        ->options(function () use ($connection, $foreignTable, $foreignCol) {
                            return $this->getForeignKeyOptions($foreignTable, $foreignCol, $connection);
                        })
                        ->searchable()
                        ->placeholder("Select {$foreignTable}");

                    if ($nullable) {
                        $field->nullable()->placeholder("NULL");
                    }

                    $fields[] = $field;
                    continue;
                }
            }

            // Map by column type
            $field = match (true) {
                // Boolean
                $this->isBooleanType($type) => Components\Toggle::make($name)
                    ->label($name),

                // Date
                $type === 'date' => Components\DatePicker::make($name)
                    ->label($name),

                // DateTime / Timestamp
                in_array($type, ['datetime', 'timestamp']) => Components\DateTimePicker::make($name)
                    ->label($name)
                    ->seconds(false),

                // Time
                $type === 'time' => Components\TimePicker::make($name)
                    ->label($name),

                // Text / Long text
                in_array($type, ['text', 'longtext', 'mediumtext', 'tinytext']) => Components\Textarea::make($name)
                    ->label($name)
                    ->rows(4),

                // JSON
                in_array($type, ['json', 'jsonb']) => Components\Textarea::make($name)
                    ->label($name)
                    ->rows(6)
                    ->helperText('Enter valid JSON'),

                // Integer types
                $this->isIntegerType($type) => Components\TextInput::make($name)
                    ->label($name)
                    ->numeric()
                    ->integer(),

                // Decimal / Float types
                $this->isDecimalType($type) => Components\TextInput::make($name)
                    ->label($name)
                    ->numeric(),

                // Enum (MySQL)
                str_starts_with($type, 'enum') => $this->buildEnumField($name, $type),

                // Default: string/varchar
                default => Components\TextInput::make($name)
                    ->label($name)
                    ->maxLength($this->extractLength($type)),
            };

            // Apply nullable
            $isNullableStringField = $nullable && (
                $field instanceof Components\TextInput ||
                $field instanceof Components\Textarea
            );

            if ($nullable) {
                $field->nullable();
                if (!($field instanceof Components\Toggle)) {
                    $field->placeholder('NULL');
                }
            }

            // Apply default
            if ($default !== null && $isInsert) {
                $field->default($this->cleanDefault($default));
            }

            // Disable auto-increment on edit
            if ($autoIncrement && !$isInsert) {
                $field->disabled();
            }

            // Password fields — show as password input
            if (Str::contains($name, ['password', 'secret', 'token'])) {
                if ($field instanceof Components\TextInput) {
                    $field->password()->revealable();
                }
            }

            $fields[] = $field;

            // Add "Set NULL" checkbox for nullable string/text fields
            if ($isNullableStringField) {
                $fields[] = Components\Checkbox::make("{$name}__null")
                    ->label("Set {$name} to NULL")
                    ->helperText('Check this to explicitly set NULL instead of empty string')
                    ->live()
                    ->afterStateUpdated(function ($state, Components\Forms\Set $set) use ($name) {
                        if ($state) {
                            $set($name, null);
                        }
                    });
            }
        }

        return $fields;
    }

    protected function getForeignKeyOptions(string $table, string $keyColumn, string $connection): array
    {
        try {
            $rows = DB::connection($connection)
                ->table($table)
                ->select([$keyColumn, $this->guessLabelColumn($table, $connection)])
                ->limit(500)
                ->get();

            $labelCol = $this->guessLabelColumn($table, $connection);

            return $rows->pluck($labelCol, $keyColumn)->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Guess a human-readable label column for a table (name, title, email, label, etc).
     */
    protected function guessLabelColumn(string $table, string $connection): string
    {
        $columns = $this->getColumns($table, $connection);
        $names = array_column($columns, 'name');

        $preferred = ['name', 'title', 'label', 'email', 'username', 'slug', 'description'];
        foreach ($preferred as $candidate) {
            if (in_array($candidate, $names)) {
                return $candidate;
            }
        }

        // Fallback: first non-id column or first column
        foreach ($names as $name) {
            if ($name !== 'id') return $name;
        }

        return $names[0] ?? 'id';
    }

    protected function isBooleanType(string $type): bool
    {
        return in_array($type, ['boolean', 'bool', 'tinyint(1)']);
    }

    protected function isIntegerType(string $type): bool
    {
        return in_array($type, [
            'integer', 'int', 'bigint', 'biginteger', 'smallint', 'smallinteger',
            'mediumint', 'mediuminteger', 'tinyint', 'tinyinteger',
            'int4', 'int8', 'int2', // postgres
        ]);
    }

    protected function isDecimalType(string $type): bool
    {
        return in_array($type, ['decimal', 'float', 'double', 'real', 'numeric', 'money']);
    }

    protected function buildEnumField(string $name, string $type): Components\Select
    {
        // Extract values from enum('a','b','c')
        preg_match_all("/'([^']+)'/", $type, $matches);
        $options = $matches[1] ?? [];

        return Components\Select::make($name)
            ->label($name)
            ->options(array_combine($options, $options));
    }

    protected function extractLength(string $type): ?int
    {
        if (preg_match('/\((\d+)\)/', $type, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    protected function cleanDefault(mixed $default): mixed
    {
        if (is_string($default)) {
            // Strip SQL-isms like ::character varying, quotes, etc.
            $default = preg_replace("/^'(.*)'$/", '$1', $default);
            $default = preg_replace('/::.*$/', '', $default);
        }
        return $default;
    }
}
