<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Connections
    |--------------------------------------------------------------------------
    |
    | Which database connections can be managed. Use null to allow all
    | connections defined in config('database.connections'), or provide
    | an array of connection names to whitelist.
    |
    */
    'connections' => null,

    /*
    |--------------------------------------------------------------------------
    | Read-Only Mode
    |--------------------------------------------------------------------------
    |
    | When true, only SELECT/browse operations are permitted. All DDL and
    | DML write operations will be blocked.
    |
    */
    'read_only' => false,

    /*
    |--------------------------------------------------------------------------
    | Max Rows Per Page
    |--------------------------------------------------------------------------
    */
    'rows_per_page' => 25,

    /*
    |--------------------------------------------------------------------------
    | Enable SQL Query Runner
    |--------------------------------------------------------------------------
    */
    'query_runner' => true,

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
