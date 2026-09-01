<?php

declare(strict_types=1);

namespace Crumbls\FilamentDatabase\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final class DatabaseLog
{
    private const SAFE_CONTEXT_KEYS = [
        'changed_columns',
        'column',
        'connection',
        'deleted_count',
        'duration_ms',
        'error_count',
        'exception_code',
        'exception_type',
        'filename',
        'inserted_columns',
        'operation',
        'query_hash',
        'query_length',
        'reference',
        'result_rows',
        'row_fingerprint',
        'statement',
        'success_count',
        'table',
        'type',
        'user_id',
    ];

    public static function failure(string $operation, Throwable $exception, array $context = []): string
    {
        $reference = (string) Str::uuid();

        Log::error('[filament-database] Database operation failed', self::safeContext([
            ...$context,
            'operation' => $operation,
            'reference' => $reference,
            'exception_type' => $exception::class,
            'exception_code' => substr((string) $exception->getCode(), 0, 32),
        ]));

        return "The database operation could not be completed. Reference: {$reference}";
    }

    /**
     * @return array{statement: string, query_hash: string, query_length: int}
     */
    public static function queryContext(string $sql): array
    {
        preg_match('/\A\s*([A-Za-z]+)/', $sql, $matches);

        return [
            'statement' => strtoupper($matches[1] ?? 'UNKNOWN'),
            'query_hash' => hash('sha256', $sql),
            'query_length' => strlen($sql),
        ];
    }

    public static function rowFingerprint(array $where): string
    {
        ksort($where);

        try {
            $serialized = json_encode($where, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $serialized = serialize(array_map(get_debug_type(...), $where));
        }

        $key = (string) config('app.key', 'filament-database');

        return hash_hmac('sha256', $serialized, $key);
    }

    public static function safeContext(array $context): array
    {
        return array_intersect_key($context, array_flip(self::SAFE_CONTEXT_KEYS));
    }
}
