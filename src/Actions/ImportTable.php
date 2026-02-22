<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Actions;

use Illuminate\Support\Facades\DB;

class ImportTable
{
    protected array $csvData = [];
    protected array $headers = [];
    protected array $columnMapping = [];
    protected int $successCount = 0;
    protected int $errorCount = 0;
    protected array $errors = [];

    public function __construct(
        protected string $table,
        protected string $connection
    ) {
    }

    public static function make(string $table, string $connection): self
    {
        return new self($table, $connection);
    }

    /**
     * Parse CSV file and return headers + first 5 rows for preview.
     */
    public function parseCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('CSV file not found');
        }

        $handle = fopen($filePath, 'r');
        $this->headers = fgetcsv($handle) ?: [];
        $this->csvData = [];

        // Read all rows
        while (($row = fgetcsv($handle)) !== false) {
            $this->csvData[] = $row;
        }

        fclose($handle);

        // Return preview (first 5 rows)
        return [
            'headers' => $this->headers,
            'preview' => array_slice($this->csvData, 0, 5),
            'total' => count($this->csvData),
        ];
    }

    /**
     * Auto-match CSV headers to table columns.
     */
    public function autoMapColumns(): array
    {
        $tableColumns = DB::connection($this->connection)
            ->getSchemaBuilder()
            ->getColumns($this->table);

        $tableColumnNames = array_column($tableColumns, 'name');
        $mapping = [];

        foreach ($this->headers as $index => $csvHeader) {
            // Exact match
            if (in_array($csvHeader, $tableColumnNames)) {
                $mapping[$index] = $csvHeader;
            } else {
                // Try case-insensitive match
                $lower = strtolower($csvHeader);
                foreach ($tableColumnNames as $col) {
                    if (strtolower($col) === $lower) {
                        $mapping[$index] = $col;
                        break;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Import rows using the provided column mapping.
     */
    public function import(array $columnMapping): array
    {
        $this->columnMapping = $columnMapping;
        $this->successCount = 0;
        $this->errorCount = 0;
        $this->errors = [];

        foreach ($this->csvData as $rowIndex => $row) {
            try {
                $data = [];

                foreach ($columnMapping as $csvIndex => $tableColumn) {
                    if ($tableColumn && isset($row[$csvIndex])) {
                        $value = $row[$csvIndex];
                        // Convert empty strings to NULL
                        $data[$tableColumn] = $value === '' ? null : $value;
                    }
                }

                if (!empty($data)) {
                    DB::connection($this->connection)
                        ->table($this->table)
                        ->insert($data);

                    $this->successCount++;
                }
            } catch (\Throwable $e) {
                $this->errorCount++;
                $this->errors[] = "Row {$rowIndex}: " . $e->getMessage();
            }
        }

        return [
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'error_messages' => $this->errors,
        ];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getCsvData(): array
    {
        return $this->csvData;
    }
}
