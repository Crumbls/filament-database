<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Actions;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTable
{
    public function __construct(
        protected string $table,
        protected string $connection,
        protected string $format,
        protected bool $allRows = false,
        protected ?array $currentPageData = null
    ) {
    }

    public static function make(string $table, string $connection, string $format, bool $allRows = false, ?array $currentPageData = null): self
    {
        return new self($table, $connection, $format, $allRows, $currentPageData);
    }

    public function download(): StreamedResponse
    {
        $filename = $this->table . '_' . date('Y-m-d_His') . '.' . $this->getExtension();

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            match ($this->format) {
                'csv' => $this->exportCsv($handle),
                'json' => $this->exportJson($handle),
                'sql' => $this->exportSql($handle),
                default => throw new \InvalidArgumentException("Unsupported format: {$this->format}"),
            };

            fclose($handle);
        }, 200, [
            'Content-Type' => $this->getContentType(),
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function getData(): \Illuminate\Support\LazyCollection
    {
        if (!$this->allRows && $this->currentPageData !== null) {
            // Return current page data as a lazy collection
            return \Illuminate\Support\LazyCollection::make($this->currentPageData);
        }

        // Stream all rows
        return DB::connection($this->connection)
            ->table($this->table)
            ->cursor();
    }

    protected function exportCsv($handle): void
    {
        $firstRow = true;

        foreach ($this->getData() as $row) {
            $row = (array) $row;

            if ($firstRow) {
                fputcsv($handle, array_keys($row));
                $firstRow = false;
            }

            fputcsv($handle, array_values($row));
        }
    }

    protected function exportJson($handle): void
    {
        fwrite($handle, '[');
        $first = true;

        foreach ($this->getData() as $row) {
            if (!$first) {
                fwrite($handle, ',');
            }
            fwrite($handle, json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $first = false;
        }

        fwrite($handle, ']');
    }

    protected function exportSql($handle): void
    {
        $columns = DB::connection($this->connection)
            ->getSchemaBuilder()
            ->getColumns($this->table);

        $columnNames = array_column($columns, 'name');

        foreach ($this->getData() as $row) {
            $row = (array) $row;
            $values = [];

            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $values[] = $value;
                } else {
                    $values[] = "'" . addslashes((string) $value) . "'";
                }
            }

            $sql = sprintf(
                "INSERT INTO `%s` (%s) VALUES (%s);\n",
                $this->table,
                implode(', ', array_map(fn($c) => "`{$c}`", $columnNames)),
                implode(', ', $values)
            );

            fwrite($handle, $sql);
        }
    }

    protected function getExtension(): string
    {
        return match ($this->format) {
            'csv' => 'csv',
            'json' => 'json',
            'sql' => 'sql',
            default => 'txt',
        };
    }

    protected function getContentType(): string
    {
        return match ($this->format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'sql' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
