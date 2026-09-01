<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;

final class DatabaseAccessBoundaryPage extends DatabaseManager
{
    public function bootedInteractsWithTable(): void
    {
        // The access-boundary tests do not need to render Filament's table schema.
    }

    public function render(): View
    {
        return view()->file(__DIR__ . '/../Fixtures/empty.blade.php');
    }

    public function resetTable(): void
    {
        // The access-boundary tests do not render table records.
    }
}

function registerDatabaseManagerPanel(FilamentDatabasePlugin $plugin): void
{
    $panel = Panel::make()
        ->id('database-access-boundary')
        ->default()
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
}

describe('Database manager access boundary', function () {
    beforeEach(function () {
        app()->instance(DataStore::class, new DataStore());
    });

    it('rejects client attempts to replace the active connection', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing']),
        );

        expect(
            fn () => Livewire::test(DatabaseAccessBoundaryPage::class)
                ->set('activeConnection', 'secondary'),
        )->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('rejects client attempts to replace the active table', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing']),
        );

        expect(
            fn () => Livewire::test(DatabaseAccessBoundaryPage::class)
                ->set('activeTable', 'users'),
        )->toThrow(CannotUpdateLockedPropertyException::class);
    });

    it('blocks direct database method calls against an excluded connection', function () {
        Schema::connection('secondary')->create('private_records', function (Blueprint $table): void {
            $table->id();
            $table->string('value');
        });

        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('getRows', 'private_records', 'secondary')
            ->assertForbidden();
    });

    it('does not switch to an excluded connection', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('switchConnection', 'secondary')
            ->assertSet('activeConnection', 'testing');
    });

    it('reauthorizes direct database calls on every request', function () {
        $isAuthorized = true;

        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(function () use (&$isAuthorized): bool {
                    return $isAuthorized;
                })
                ->connections(['testing']),
        );

        $component = Livewire::test(DatabaseAccessBoundaryPage::class);
        $isAuthorized = false;

        $component
            ->call('getRows', 'categories', 'testing')
            ->assertForbidden();
    });

    it('blocks destructive calls against a hidden table without changing it', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing'])
                ->hideTables(['users']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('confirmDropTable', 'users')
            ->assertForbidden();

        expect(Schema::connection('testing')->hasTable('users'))->toBeTrue();
    });

    it('does not disclose hidden tables through direct metadata calls', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing'])
                ->hideTables(['users']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('getTables', 'testing')
            ->assertReturned(
                fn (array $tables): bool => ! collect($tables)
                    ->pluck('name')
                    ->contains('users'),
            );
    });

    it('does not disclose hidden tables through relationship metadata', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing'])
                ->hideTables(['users']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('getTableRelationships', 'posts', 'testing')
            ->assertReturned(
                fn (array $relationships): bool => ! collect($relationships['references'])
                    ->pluck('foreign_table')
                    ->contains('users'),
            );
    });

    it('blocks direct reads from a hidden table', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing'])
                ->hideTables(['users']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('getRows', 'users', 'testing')
            ->assertForbidden();
    });

    it('allows selecting an existing visible table on an allowed connection', function () {
        registerDatabaseManagerPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->connections(['testing']),
        );

        Livewire::test(DatabaseAccessBoundaryPage::class)
            ->call('selectTable', 'categories')
            ->assertSet('activeConnection', 'testing')
            ->assertSet('activeTable', 'categories');
    });
});
