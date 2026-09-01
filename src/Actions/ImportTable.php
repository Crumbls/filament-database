<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Actions;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportTable
{
    public const MAX_FILE_SIZE_KILOBYTES = 10_240;

    private const MAX_CSV_COLUMNS = 512;

    private const MAX_CSV_ROWS = 50_000;

    protected array $csvData = [];

    protected array $headers = [];

    protected array $columnMapping = [];

    protected int $successCount = 0;

    protected int $errorCount = 0;

    protected array $errors = [];

    public function __construct(
        protected string $table,
        protected string $connection,
    ) {
    }

    public static function make(string $table, string $connection): self
    {
        return new self($table, $connection);
    }

    /**
     * Parse a bounded CSV file and return its headers and first five rows.
     */
    public function parseCsv(string $filePath): array
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('CSV file not found or is not readable.');
        }

        $fileSize = filesize($filePath);

        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE_KILOBYTES * 1024) {
            throw new RuntimeException('CSV files may not exceed 10 MB.');
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('CSV file could not be opened.');
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '');

            if ($headers === false) {
                throw new RuntimeException('CSV file must contain a header row.');
            }

            $this->headers = $this->normalizeHeaders($headers);
            $this->csvData = [];

            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                if (count($row) !== count($this->headers)) {
                    throw new RuntimeException(sprintf(
                        'CSV row %d contains %d columns; expected %d.',
                        count($this->csvData) + 2,
                        count($row),
                        count($this->headers),
                    ));
                }

                if (count($this->csvData) >= self::MAX_CSV_ROWS) {
                    throw new RuntimeException(sprintf(
                        'CSV files may not contain more than %d data rows.',
                        self::MAX_CSV_ROWS,
                    ));
                }

                $this->csvData[] = $row;
            }
        } finally {
            fclose($handle);
        }

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
        $caseInsensitiveNames = array_combine(
            array_map(strtolower(...), $tableColumnNames),
            $tableColumnNames,
        );
        $mapping = [];

        foreach ($this->headers as $index => $csvHeader) {
            if (in_array($csvHeader, $tableColumnNames, true)) {
                $mapping[$index] = $csvHeader;
            } elseif (isset($caseInsensitiveNames[strtolower($csvHeader)])) {
                $mapping[$index] = $caseInsensitiveNames[strtolower($csvHeader)];
            }
        }

        return $mapping;
    }

    /**
     * Import rows using a validated column mapping.
     */
    public function import(array $columnMapping): array
    {
        $this->columnMapping = $this->validateColumnMapping($columnMapping);
        $this->successCount = 0;
        $this->errorCount = 0;
        $this->errors = [];

        $connection = DB::connection($this->connection);

        foreach (array_chunk($this->csvData, 500, preserve_keys: true) as $rows) {
            $chunk = [];

            foreach ($rows as $rowIndex => $row) {
                $data = [];

                foreach ($this->columnMapping as $csvIndex => $tableColumn) {
                    $data[$tableColumn] = $row[$csvIndex] === '' ? null : $row[$csvIndex];
                }

                $chunk[] = ['index' => $rowIndex + 2, 'data' => $data];
            }

            $batch = array_column($chunk, 'data');

            try {
                $connection->transaction(
                    fn (): bool => $connection->table($this->table)->insert($batch),
                );
                $this->successCount += count($batch);
            } catch (Throwable) {
                foreach ($chunk as $item) {
                    try {
                        $connection->transaction(
                            fn (): bool => $connection->table($this->table)->insert($item['data']),
                        );
                        $this->successCount++;
                    } catch (Throwable $exception) {
                        $this->errorCount++;
                        $this->errors[] = "Row {$item['index']}: " . $exception->getMessage();
                    }
                }
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

    protected function normalizeHeaders(array $headers): array
    {
        if ($headers === [] || count($headers) > self::MAX_CSV_COLUMNS) {
            throw new RuntimeException(sprintf(
                'CSV files must contain between 1 and %d columns.',
                self::MAX_CSV_COLUMNS,
            ));
        }

        $normalized = [];
        $seen = [];

        foreach ($headers as $index => $header) {
            if (! is_string($header)) {
                throw new RuntimeException('CSV headers must be strings.');
            }

            if ($index === 0) {
                $header = preg_replace('/\A\xEF\xBB\xBF/', '', $header) ?? $header;
            }

            $header = trim($header);

            if ($header === '' || strlen($header) > 128) {
                throw new RuntimeException('CSV headers must be non-empty and at most 128 characters.');
            }

            $normalizedHeader = strtolower($header);

            if (isset($seen[$normalizedHeader])) {
                throw new RuntimeException("Duplicate CSV header: {$header}");
            }

            $seen[$normalizedHeader] = true;
            $normalized[] = $header;
        }

        return $normalized;
    }

    protected function validateColumnMapping(array $columnMapping): array
    {
        $tableColumns = DB::connection($this->connection)
            ->getSchemaBuilder()
            ->getColumnListing($this->table);
        $allowedColumns = array_fill_keys($tableColumns, true);
        $mappedColumns = [];
        $validated = [];

        foreach ($columnMapping as $csvIndex => $tableColumn) {
            if (is_string($csvIndex) && ctype_digit($csvIndex)) {
                $csvIndex = (int) $csvIndex;
            }

            if (! is_int($csvIndex) || $csvIndex < 0 || $csvIndex >= count($this->headers)) {
                throw new RuntimeException('CSV column mapping contains an invalid source index.');
            }

            if ($tableColumn === null || $tableColumn === '') {
                continue;
            }

            if (! is_string($tableColumn) || ! isset($allowedColumns[$tableColumn])) {
                throw new RuntimeException('CSV column mapping contains an invalid target column.');
            }

            if (isset($mappedColumns[$tableColumn])) {
                throw new RuntimeException("CSV column mapping targets {$tableColumn} more than once.");
            }

            $mappedColumns[$tableColumn] = true;
            $validated[$csvIndex] = $tableColumn;
        }

        if ($validated === []) {
            throw new RuntimeException('At least one CSV column must map to the destination table.');
        }

        return $validated;
    }
}
