<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;

describe('Plugin Configuration', function () {

    it('uses the configured read-only safety default', function () {
        config()->set('filament-database.read_only', true);

        $plugin = new FilamentDatabasePlugin();

        expect($plugin->isReadOnly())->toBeTrue();
    });

    it('enables read-only mode', function () {
        $plugin = (new FilamentDatabasePlugin())->readOnly();
        expect($plugin->isReadOnly())->toBeTrue();
    });

    it('allows fluent configuration to disable configured read-only mode', function () {
        config()->set('filament-database.read_only', true);

        $plugin = (new FilamentDatabasePlugin())->readOnly(false);

        expect($plugin->isReadOnly())->toBeFalse();
    });

    it('defaults to not preventDestructive', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->isDestructivePrevented())->toBeFalse();
    });

    it('enables preventDestructive', function () {
        $plugin = (new FilamentDatabasePlugin())->preventDestructive();
        expect($plugin->isDestructivePrevented())->toBeTrue();
    });

    it('defaults confirmation not required', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->isConfirmationRequired())->toBeFalse();
    });

    it('enables requireConfirmation', function () {
        $plugin = (new FilamentDatabasePlugin())->requireConfirmation();
        expect($plugin->isConfirmationRequired())->toBeTrue();
    });

    it('whitelists connections', function () {
        $plugin = (new FilamentDatabasePlugin())->connections(['testing']);
        $allowed = $plugin->getAllowedConnections();

        expect($allowed)->toContain('testing')
            ->and($allowed)->not->toContain('secondary');
    });

    it('excludes connections', function () {
        $plugin = (new FilamentDatabasePlugin())
            ->connections(['testing', 'secondary'])
            ->excludeConnections(['secondary']);
        $allowed = $plugin->getAllowedConnections();

        expect($allowed)->toContain('testing')
            ->and($allowed)->not->toContain('secondary');
    });

    it('sets default connection', function () {
        $plugin = (new FilamentDatabasePlugin())->defaultConnection('secondary');
        expect($plugin->getDefaultConnection())->toBe('secondary');
    });

    it('falls back to config default connection', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->getDefaultConnection())->toBe(config('database.default'));
    });

    it('hides tables', function () {
        $plugin = (new FilamentDatabasePlugin())->hideTables(['migrations', 'jobs']);

        expect($plugin->getHiddenTables())->toBe(['migrations', 'jobs'])
            ->and($plugin->isTableVisible('migrations'))->toBeFalse()
            ->and($plugin->isTableVisible('users'))->toBeTrue();
    });

    it('shows only specified tables', function () {
        $plugin = (new FilamentDatabasePlugin())->showOnlyTables(['users', 'posts']);

        expect($plugin->getVisibleTables())->toBe(['users', 'posts'])
            ->and($plugin->isTableVisible('users'))->toBeTrue()
            ->and($plugin->isTableVisible('categories'))->toBeFalse();
    });

    it('sets navigation group', function () {
        $plugin = (new FilamentDatabasePlugin())->navigationGroup('Admin');
        expect($plugin->getNavigationGroup())->toBe('Admin');
    });

    it('sets navigation icon', function () {
        $plugin = (new FilamentDatabasePlugin())->navigationIcon('heroicon-o-database');
        expect($plugin->getNavigationIcon())->toBe('heroicon-o-database');
    });

    it('sets navigation sort', function () {
        $plugin = (new FilamentDatabasePlugin())->navigationSort(50);
        expect($plugin->getNavigationSort())->toBe(50);
    });

    it('sets navigation label', function () {
        $plugin = (new FilamentDatabasePlugin())->navigationLabel('DB Manager');
        expect($plugin->getNavigationLabel())->toBe('DB Manager');
    });

    it('defaults to null navigation values', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->getNavigationGroup())->toBeNull()
            ->and($plugin->getNavigationIcon())->toBeNull()
            ->and($plugin->getNavigationSort())->toBeNull()
            ->and($plugin->getNavigationLabel())->toBeNull();
    });

    it('sets rows per page', function () {
        $plugin = (new FilamentDatabasePlugin())->rowsPerPage(50);
        expect($plugin->getRowsPerPage())->toBe(50);
    });

    it('uses configured rows per page', function () {
        config()->set('filament-database.rows_per_page', 75);

        $plugin = new FilamentDatabasePlugin();

        expect($plugin->getRowsPerPage())->toBe(75);
    });

    it('clamps configured rows per page to safe bounds', function () {
        config()->set('filament-database.rows_per_page', 1000);

        $plugin = (new FilamentDatabasePlugin())->maxRowsPerPage(200);

        expect($plugin->getRowsPerPage())->toBe(200)
            ->and($plugin->rowsPerPage(0)->getRowsPerPage())->toBe(1);
    });

    it('sets max rows per page', function () {
        $plugin = (new FilamentDatabasePlugin())->maxRowsPerPage(1000);
        expect($plugin->getMaxRowsPerPage())->toBe(1000);
    });

    it('defaults max rows per page to 500', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->getMaxRowsPerPage())->toBe(500);
    });

    it('enables logQueries', function () {
        $plugin = (new FilamentDatabasePlugin())->logQueries();
        expect($plugin->shouldLogQueries())->toBeTrue();
    });

    it('defaults logQueries to false', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->shouldLogQueries())->toBeFalse();
    });

    it('enables logChanges', function () {
        $plugin = (new FilamentDatabasePlugin())->logChanges();
        expect($plugin->shouldLogChanges())->toBeTrue();
    });

    it('defaults logChanges to false', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->shouldLogChanges())->toBeFalse();
    });

    it('disables query runner', function () {
        $plugin = (new FilamentDatabasePlugin())->disableQueryRunner();
        expect($plugin->isQueryRunnerEnabled())->toBeFalse();
    });

    it('uses the configured query runner safety default', function () {
        config()->set('filament-database.query_runner', false);

        $plugin = new FilamentDatabasePlugin();

        expect($plugin->isQueryRunnerEnabled())->toBeFalse();
    });

    it('allows fluent configuration to enable a disabled query runner', function () {
        config()->set('filament-database.query_runner', false);

        $plugin = (new FilamentDatabasePlugin())->disableQueryRunner(false);

        expect($plugin->isQueryRunnerEnabled())->toBeTrue();
    });

    it('sets query runner to read-only', function () {
        $plugin = (new FilamentDatabasePlugin())->queryRunnerReadOnly();
        expect($plugin->isQueryRunnerReadOnly())->toBeTrue();
    });

    it('inherits read-only to query runner', function () {
        $plugin = (new FilamentDatabasePlugin())->readOnly();
        expect($plugin->isQueryRunnerReadOnly())->toBeTrue();
    });

    it('returns plugin id', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->getId())->toBe('filament-database');
    });

    it('validates connections', function () {
        $plugin = (new FilamentDatabasePlugin())->connections(['testing']);
        expect($plugin->isConnectionValid('testing'))->toBeTrue()
            ->and($plugin->isConnectionValid('nonexistent'))->toBeFalse();
    });

    it('tests healthy connection', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->testConnection('testing'))->toBeTrue();
    });

    it('tests unhealthy connection returns error string', function () {
        config(['database.connections.bad' => ['driver' => 'sqlite', 'database' => '/nonexistent/path/db.sqlite']]);
        $plugin = new FilamentDatabasePlugin();
        $result = $plugin->testConnection('bad');
        expect($result)->toBeString();
    });

    it('supports fluent chaining', function () {
        $plugin = (new FilamentDatabasePlugin())
            ->readOnly()
            ->preventDestructive()
            ->requireConfirmation()
            ->connections(['testing'])
            ->excludeConnections(['secondary'])
            ->defaultConnection('testing')
            ->hideTables(['migrations'])
            ->navigationGroup('System')
            ->navigationIcon('heroicon-o-circle-stack')
            ->navigationSort(100)
            ->navigationLabel('Database')
            ->rowsPerPage(50)
            ->maxRowsPerPage(200)
            ->logQueries()
            ->logChanges()
            ->disableQueryRunner()
            ->queryRunnerReadOnly();

        expect($plugin)->toBeInstanceOf(FilamentDatabasePlugin::class)
            ->and($plugin->isReadOnly())->toBeTrue()
            ->and($plugin->isDestructivePrevented())->toBeTrue()
            ->and($plugin->isConfirmationRequired())->toBeTrue();
    });
});
