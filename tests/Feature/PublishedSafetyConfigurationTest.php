<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    it('rejects direct row mutations when read-only is enabled only in config', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.read_only', true);
        $this->seedTestData();

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->call('updateRow', 'users', ['id' => 1], ['name' => 'Changed'], 'testing')
            ->assertForbidden();

        expect(DB::connection('testing')->table('users')->where('id', 1)->value('name'))
            ->toBe('Alice');
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

    it('logs query metadata without logging SQL text or literal values', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.query_runner', true);
        Log::spy();

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())
                ->authorize(fn (): bool => true)
                ->logQueries(),
        );

        $secretQuery = "SELECT 'super-secret-value' AS credential";

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->set('sqlQuery', $secretQuery)
            ->call('executeSql')
            ->assertSet('sqlError', '');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($secretQuery): bool {
                return $message === '[filament-database] SQL execution requested'
                    && $context['statement'] === 'SELECT'
                    && $context['query_hash'] === hash('sha256', $secretQuery)
                    && $context['query_length'] === strlen($secretQuery)
                    && ! array_key_exists('query', $context)
                    && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'super-secret-value');
            });
    });

    it('does not expose raw database errors to the browser or application log', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.query_runner', true);
        Log::spy();

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        $secret = 'super_secret_customer_table';
        $component = Livewire::test(PublishedSafetyConfigurationPage::class)
            ->set('sqlQuery', "SELECT * FROM {$secret}")
            ->call('executeSql');
        $error = $component->get('sqlError');

        expect($error)
            ->toStartWith('The database operation could not be completed. Reference: ')
            ->not->toContain($secret);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($secret): bool {
                return $message === '[filament-database] Database operation failed'
                    && $context['operation'] === 'sql_execute'
                    && $context['statement'] === 'SELECT'
                    && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $secret);
            });
    });

    it('prevents writes disguised as explain analyze in read-only mode', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.read_only', true);
        config()->set('filament-database.query_runner', true);
        $this->seedTestData();

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        $rowCount = DB::connection('testing')->table('categories')->count();

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->set('sqlQuery', 'EXPLAIN ANALYZE DELETE FROM categories')
            ->call('executeSql')
            ->assertSet(
                'sqlError',
                'SQL runner is in read-only mode: only read-only queries are allowed.',
            );

        expect(DB::connection('testing')->table('categories')->count())
            ->toBe($rowCount);
    });

    it('rejects direct write queries while the runner is read-only', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.read_only', true);
        config()->set('filament-database.query_runner', true);
        $this->seedTestData();

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        $rowCount = DB::connection('testing')->table('categories')->count();

        expect(
            fn () => Livewire::test(PublishedSafetyConfigurationPage::class)
                ->call('runQuery', 'DELETE FROM categories', 'testing'),
        )->toThrow(InvalidArgumentException::class, 'Only read-only SQL is permitted.');

        expect(DB::connection('testing')->table('categories')->count())
            ->toBe($rowCount);
    });

    it('rejects direct query method calls when the SQL runner is disabled', function () {
        config()->set('filament-database.connections', ['testing']);
        config()->set('filament-database.query_runner', false);

        registerPublishedSafetyConfigurationPanel(
            (new FilamentDatabasePlugin())->authorize(fn (): bool => true),
        );

        Livewire::test(PublishedSafetyConfigurationPage::class)
            ->call('runQuery', 'SELECT * FROM categories', 'testing')
            ->assertForbidden();
    });
});
