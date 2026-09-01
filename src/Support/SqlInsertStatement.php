<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Support;

use BackedEnum;
use Illuminate\Database\Connection;
use JsonException;
use RuntimeException;
use Stringable;

final readonly class SqlInsertStatement
{
    public function __construct(private Connection $connection)
    {
    }

    public function build(string $table, array $row): string
    {
        if ($row === []) {
            throw new \InvalidArgumentException('SQL INSERT rows may not be empty.');
        }

        $grammar = $this->connection->getQueryGrammar();
        $columns = array_map(
            static fn (string $column): string => $grammar->wrap($column),
            array_keys($row),
        );
        $values = array_map($this->literal(...), array_values($row));

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s);',
            $grammar->wrapTable($table),
            implode(', ', $columns),
            implode(', ', $values),
        );
    }

    protected function literal(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            $value instanceof BackedEnum => $this->literal($value->value),
            is_bool($value) => $this->booleanLiteral($value),
            is_int($value) => (string) $value,
            is_float($value) => $this->floatLiteral($value),
            is_string($value) => $this->quotedString($value),
            $value instanceof Stringable => $this->quotedString((string) $value),
            default => throw new \InvalidArgumentException(sprintf(
                'Unsupported SQL literal type: %s.',
                get_debug_type($value),
            )),
        };
    }

    protected function booleanLiteral(bool $value): string
    {
        if ($this->connection->getDriverName() === 'pgsql') {
            return $value ? 'TRUE' : 'FALSE';
        }

        return $value ? '1' : '0';
    }

    protected function floatLiteral(float $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Non-finite floats cannot be exported to SQL.', previous: $exception);
        }
    }

    protected function quotedString(string $value): string
    {
        $quoted = $this->connection->getPdo()->quote($value);

        if ($quoted === false) {
            throw new RuntimeException('The database driver could not quote an exported value.');
        }

        return $quoted;
    }
}
