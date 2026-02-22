<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;

describe('Connection Management', function () {

    it('lists available connections', function () {
        $plugin = new FilamentDatabasePlugin();
        $connections = $plugin->getAllowedConnections();

        expect($connections)->toBeArray()
            ->and($connections)->toContain('testing');
    });

    it('validates a known connection', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->isConnectionValid('testing'))->toBeTrue();
    });

    it('rejects unknown connections', function () {
        $plugin = (new FilamentDatabasePlugin())->connections(['testing']);
        expect($plugin->isConnectionValid('nonexistent'))->toBeFalse();
    });

    it('excludes specified connections', function () {
        $plugin = (new FilamentDatabasePlugin())->excludeConnections(['secondary']);
        $allowed = $plugin->getAllowedConnections();

        expect($allowed)->not->toContain('secondary');
    });

    it('selects default connection from config', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->getDefaultConnection())->toBe(config('database.default'));
    });

    it('overrides default connection', function () {
        $plugin = (new FilamentDatabasePlugin())->defaultConnection('secondary');
        expect($plugin->getDefaultConnection())->toBe('secondary');
    });

    it('tests healthy connection returns true', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->testConnection('testing'))->toBeTrue();
    });

    it('tests unhealthy connection returns error string', function () {
        // Use a SQLite file that doesn't exist to get an instant error
        config(['database.connections.broken' => [
            'driver' => 'sqlite',
            'database' => '/nonexistent/path/to/db.sqlite',
        ]]);
        $plugin = new FilamentDatabasePlugin();
        $result = $plugin->testConnection('broken');
        expect($result)->toBeString();
    });

    it('whitelists only specified connections', function () {
        $plugin = (new FilamentDatabasePlugin())->connections(['testing']);
        $allowed = $plugin->getAllowedConnections();

        expect($allowed)->toBe(['testing']);
    });

    it('intersects whitelist with actual config', function () {
        $plugin = (new FilamentDatabasePlugin())->connections(['testing', 'imaginary']);
        $allowed = $plugin->getAllowedConnections();

        expect($allowed)->toContain('testing')
            ->and($allowed)->not->toContain('imaginary');
    });
});
