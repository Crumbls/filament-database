<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;

final class PublishedSafetyConfigurationPage extends DatabaseManager
{
    public function bootedInteractsWithTable(): void
    {
        // These tests exercise page actions without rendering a Filament table.
    }

    public function render(): View
    {
        return view()->file(__DIR__ . '/../Fixtures/empty.blade.php');
    }

    public function resetTable(): void
    {
        // These tests do not render table records.
    }
}

function registerPublishedSafetyConfigurationPanel(FilamentDatabasePlugin $plugin): void
{
    $panel = Panel::make()
        ->id('published-safety-configuration')
        ->default()
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
}

describe('Published safety configuration', function () {
    beforeEach(function () {
        app()->instance(DataStore::class, new DataStore());
    });

    it('mounts without a database target when no connection is configured', function () {
        config()->set('filament-database.connections', []);

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->assertSet('activeConnection', '');
    });

    it('prevents destructive operations when read-only is enabled only in config', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.read_only', true);
        $this->seedTestData();

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        $rowCount = DB::connection('testing')->table('categories')->count();

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->call('confirmTruncateTable', 'categories');

        expect(DB::connection('testing')->table('categories')->count())
            ->toBe($rowCount);
    });

    it('prevents SQL execution when the runner is disabled only in config', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.read_only', false);
        config()->set('filament-database.query_runner', false);

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->set('sqlQuery', "INSERT INTO categories (name) VALUES ('Forbidden')")
            ->call('executeSql')
            ->assertSet('sqlError', 'SQL runner is disabled.');

        expect(DB::connection('testing')->table('categories')->count())->toBe(0);
    });
});
