<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\Support\DatabaseLog;
use Illuminate\Support\Facades\Log;

it('logs failure metadata without exception messages or unsafe context', function () {
    Log::spy();

    $secret = 'password=super-secret';
    $userMessage = DatabaseLog::failure(
        'row_update',
        new RuntimeException("SQL failed with {$secret}", 23000),
        [
            'connection' => 'testing',
            'table' => 'users',
            'query' => "UPDATE users SET password = '{$secret}'",
            'data' => ['password' => $secret],
        ],
    );

    expect($userMessage)
        ->toStartWith('The database operation could not be completed. Reference: ')
        ->not->toContain($secret);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($secret): bool {
            $encodedContext = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === '[filament-database] Database operation failed'
                && $context['operation'] === 'row_update'
                && $context['connection'] === 'testing'
                && $context['table'] === 'users'
                && $context['exception_type'] === RuntimeException::class
                && $context['exception_code'] === '23000'
                && ! array_key_exists('query', $context)
                && ! array_key_exists('data', $context)
                && ! str_contains($encodedContext, $secret);
        });
});

it('represents queries with non-reversible metadata', function () {
    $sql = "UPDATE users SET password = 'super-secret' WHERE id = 1";
    $context = DatabaseLog::queryContext($sql);

    expect($context)->toBe([
        'statement' => 'UPDATE',
        'query_hash' => hash('sha256', $sql),
        'query_length' => strlen($sql),
    ])->and(json_encode($context, JSON_THROW_ON_ERROR))->not->toContain('super-secret');
});

it('creates stable keyed row fingerprints without exposing key values', function () {
    $first = DatabaseLog::rowFingerprint(['id' => 'customer-secret-42']);
    $same = DatabaseLog::rowFingerprint(['id' => 'customer-secret-42']);
    $different = DatabaseLog::rowFingerprint(['id' => 'customer-secret-43']);

    expect($first)
        ->toBe($same)
        ->not->toBe($different)
        ->not->toContain('customer-secret-42')
        ->toHaveLength(64);
});
