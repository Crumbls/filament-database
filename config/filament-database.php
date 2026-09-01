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
