<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

describe('Access Control', function () {

    it('allows access by default with no gate', function () {
        $plugin = new FilamentDatabasePlugin();
        expect($plugin->canAccess())->toBeTrue();
    });

    it('restricts access with authorize closure - denied', function () {
        $plugin = (new FilamentDatabasePlugin())->authorize(fn ($user) => false);
        expect($plugin->canAccess())->toBeFalse();
    });

    it('allows access with authorize closure - granted', function () {
        $plugin = (new FilamentDatabasePlugin())->authorize(fn ($user) => true);
        expect($plugin->canAccess())->toBeTrue();
    });

    it('restricts access via authorizeUsing gate string', function () {
        Gate::define('manage-database', fn () => false);
        $plugin = (new FilamentDatabasePlugin())->authorizeUsing('manage-database');

        expect($plugin->canAccess())->toBeFalse();
    });

    it('allows access via authorizeUsing gate string when gate returns true', function () {
        Gate::define('manage-database', fn (?object $user = null) => true);
        $plugin = (new FilamentDatabasePlugin())->authorizeUsing('manage-database');

        expect($plugin->canAccess())->toBeTrue();
    });

    it('restricts access with onlyForEmails - no user', function () {
        Auth::shouldReceive('user')->andReturn(null);
        $plugin = (new FilamentDatabasePlugin())->onlyForEmails(['admin@example.com']);

        expect($plugin->canAccess())->toBeFalse();
    });

    it('allows access with onlyForEmails - matching email', function () {
        $user = new class extends Authenticatable {
            public string $email = 'admin@example.com';
        };
        Auth::shouldReceive('user')->andReturn($user);

        $plugin = (new FilamentDatabasePlugin())->onlyForEmails(['admin@example.com']);
        expect($plugin->canAccess())->toBeTrue();
    });

    it('is case-insensitive for email matching', function () {
        $user = new class extends Authenticatable {
            public string $email = 'Admin@Example.COM';
        };
        Auth::shouldReceive('user')->andReturn($user);

        $plugin = (new FilamentDatabasePlugin())->onlyForEmails(['admin@example.com']);
        expect($plugin->canAccess())->toBeTrue();
    });

    it('denies access with onlyForEmails - non-matching email', function () {
        $user = new class extends Authenticatable {
            public string $email = 'other@example.com';
        };
        Auth::shouldReceive('user')->andReturn($user);

        $plugin = (new FilamentDatabasePlugin())->onlyForEmails(['admin@example.com']);
        expect($plugin->canAccess())->toBeFalse();
    });
});
