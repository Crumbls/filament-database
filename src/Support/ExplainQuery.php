<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Support;

use InvalidArgumentException;

final readonly class ExplainQuery
{
    private const RESULT_MYSQL_ANALYZE = 'mysql_analyze';

    private const RESULT_POSTGRES_TEXT = 'postgres_text';

    private const RESULT_TABLE = 'table';

    private function __construct(
        public string $sql,
        public string $format,
        private string $resultMode,
    ) {
    }

    public static function build(string $driver, string $sql, string $type = 'explain'): self
    {
        $sql = trim($sql);

        if (preg_match('/\ASELECT\b/i', $sql) !== 1) {
            throw new InvalidArgumentException('EXPLAIN only supports SELECT statements.');
        }

        if (! in_array($type, ['explain', 'analyze'], true)) {
            throw new InvalidArgumentException("Unsupported EXPLAIN type: {$type}");
        }

        return match ($driver) {
            'mysql', 'mariadb' => $type === 'analyze'
                ? new self("EXPLAIN ANALYZE {$sql}", 'text', self::RESULT_MYSQL_ANALYZE)
                : new self("EXPLAIN {$sql}", 'table', self::RESULT_TABLE),
            'pgsql' => new self(
                $type === 'analyze' ? "EXPLAIN ANALYZE {$sql}" : "EXPLAIN {$sql}",
                'text',
                self::RESULT_POSTGRES_TEXT,
            ),
            'sqlite' => new self("EXPLAIN QUERY PLAN {$sql}", 'table', self::RESULT_TABLE),
            default => throw new InvalidArgumentException("EXPLAIN is not supported for driver: {$driver}"),
        };
    }

    public function formatResults(array $results): array
    {
        return match ($this->resultMode) {
            self::RESULT_MYSQL_ANALYZE => $this->formatMySqlAnalyzeResults($results),
            self::RESULT_POSTGRES_TEXT => array_map(
                static function (array|object $row): mixed {
                    $row = (array) $row;

                    return $row['QUERY PLAN'] ?? reset($row);
                },
                $results,
            ),
            default => array_map(
                static fn (array|object $row): array => (array) $row,
                $results,
            ),
        };
    }

    protected function formatMySqlAnalyzeResults(array $results): array
    {
        if (! isset($results[0])) {
            return [];
        }

        $row = (array) $results[0];

        return [reset($row)];
    }
}
