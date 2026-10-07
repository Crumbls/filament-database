<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Panel;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;

beforeEach(function () {
    app()->instance(DataStore::class, new DataStore());
    $this->seedTestData();

    $panel = Panel::make()
        ->id('database-manager-ui')
        ->default()
        ->plugin(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing'])
                ->readOnly(false)
                ->disableQueryRunner(false),
        );

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
});

it('renders a connection-level SQL workspace and a result for zero rows', function () {
    Livewire::test(DatabaseManager::class)
        ->assertSee('Environment:')
        ->assertSee('Write enabled')
        ->call('switchTab', 'sql')
        ->assertSee('SQL workspace')
        ->assertSee('Connection: testing')
        ->set('sqlQuery', 'SELECT * FROM users WHERE id = -1')
        ->call('executeSql')
        ->assertSee('Query completed. No rows returned.');
});

it('groups table tools and lets administrators return to the overview', function () {
    Livewire::test(DatabaseManager::class)
        ->call('selectTable', 'users')
        ->assertSee('Insert Row')
        ->assertSee('Transfer data')
        ->assertSee('Schema tools')
        ->assertSee('Danger zone')
        ->call('switchTab', 'overview')
        ->assertSet('activeTable', '')
        ->assertSee('Database Overview');
});

it('shows the database target in destructive confirmation', function () {
    $component = Livewire::test(DatabaseManager::class)->call('selectTable', 'users');
    $dangerGroup = collect($component->instance()->getTable()->getHeaderActions())
        ->first(fn ($action): bool => $action instanceof ActionGroup && $action->getLabel() === 'Danger zone');

    expect($dangerGroup)->toBeInstanceOf(ActionGroup::class);

    $description = $dangerGroup->getFlatActions()['truncate']->getModalDescription();

    expect($description)
        ->toContain('Connection: testing.')
        ->toContain('Environment: testing.')
        ->toContain('This cannot be undone.');
});

it('renders named, keyboard dismissible schema dialogs', function () {
    Livewire::test(DatabaseManager::class)
        ->call('openCreateTable')
        ->assertSeeHtml('aria-labelledby="fdb-create-table-title"')
        ->assertSeeHtml('for="fdb-new-table-name"')
        ->assertSeeHtml('x-on:keydown.escape.window');
});

it('opens a newly created table on its rows view', function () {
    Livewire::test(DatabaseManager::class)
        ->call('openCreateTable')
        ->set('newTableName', 'audit_notes')
        ->set('newTableColumns', [
            ['name' => 'body', 'type' => 'string', 'nullable' => false, 'primary' => false, 'autoIncrement' => false, 'arguments' => []],
        ])
        ->call('saveCreateTable')
        ->assertHasNoErrors()
        ->assertSet('activeTable', 'audit_notes')
        ->assertSet('activeTab', 'rows');
});
