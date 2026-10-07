<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Connections
    |--------------------------------------------------------------------------
    |
    | Which database connections can be managed. Connections must be
    | explicitly listed before the database manager can access them.
    |
    */
    'connections' => [],

    /*
    |--------------------------------------------------------------------------
    | Read-Only Mode
    |--------------------------------------------------------------------------
    |
    | When true, only SELECT/browse operations are permitted. All DDL and
    | DML write operations will be blocked.
    |
    */
    'read_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Rows Per Page
    |--------------------------------------------------------------------------
    */
    'rows_per_page' => 25,

    /*
    |--------------------------------------------------------------------------
    | Enable SQL Query Runner
    |--------------------------------------------------------------------------
    */
    'query_runner' => false,

    // Read-only SQL timeout for PostgreSQL, MySQL, and MariaDB (1–60 seconds).
    // SQLite needs a deployment-level execution timeout.
    'sql_statement_timeout_ms' => 5000,

    // Maximum rows returned by a single CSV, JSON, or SQL export.
    'max_export_rows' => 10_000,

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'System',
        'icon' => 'heroicon-o-circle-stack',
        'sort' => 100,
    ],
];
