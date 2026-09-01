<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;

final class RowAddressingSafetyPage extends DatabaseManager
{
    public function bootedInteractsWithTable(): void
    {
        // These tests configure the table explicitly when action metadata is needed.
    }

    public function render(): View
    {
        return view()->file(__DIR__ . '/../Fixtures/empty.blade.php');
    }

    public function resetTable(): void
    {
        // These tests do not render table records.
    }

    /**
     * @return array{record: array<int, string>, bulk_count: int}
     */
    public function configuredActionsFor(string $table): array
    {
        $this->selectTable($table);
        $configuredTable = $this->table(Table::make($this));

        return [
            'record' => array_keys($configuredTable->getFlatRecordActions()),
            'bulk_count' => count($configuredTable->getBulkActions()),
        ];
    }
}

function registerRowAddressingSafetyPanel(): void
{
    $plugin = (new FilamentDatabasePlugin())
        ->authorize(fn (): bool => true)
        ->connections(['testing'])
        ->readOnly(false);

    $panel = Panel::make()
        ->id('row-addressing-safety')
        ->default()
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
}

function createKeylessRecordsTable(): void
{
    Schema::connection('testing')->create('keyless_records', function (Blueprint $table): void {
        $table->string('group_code');
        $table->string('value');
    });

    DB::connection('testing')->table('keyless_records')->insert([
        ['group_code' => 'shared', 'value' => 'first'],
        ['group_code' => 'shared', 'value' => 'second'],
    ]);
}

function createCompositeRecordsTable(): void
{
    Schema::connection('testing')->create('composite_records', function (Blueprint $table): void {
        $table->unsignedInteger('tenant_id');
        $table->string('code');
        $table->string('value');
        $table->primary(['tenant_id', 'code']);
    });

    DB::connection('testing')->table('composite_records')->insert([
        ['tenant_id' => 1, 'code' => 'shared', 'value' => 'first'],
        ['tenant_id' => 2, 'code' => 'shared', 'value' => 'second'],
    ]);
}

describe('Row addressing safety', function () {
    beforeEach(function () {
        app()->instance(DataStore::class, new DataStore());
        registerRowAddressingSafetyPanel();
    });

    it('does not register record or selection actions for a keyless table', function () {
        createKeylessRecordsTable();

        Livewire::test(RowAddressingSafetyPage::class)
            ->call('configuredActionsFor', 'keyless_records')
            ->assertReturned([
                'record' => [],
                'bulk_count' => 0,
            ]);
    });

    it('blocks non-unique updates against a keyless table', function () {
        createKeylessRecordsTable();

        Livewire::test(RowAddressingSafetyPage::class)
            ->call(
                'updateRow',
                'keyless_records',
                ['group_code' => 'shared'],
                ['value' => 'changed'],
                'testing',
            )
            ->assertForbidden();

        expect(
            DB::connection('testing')
                ->table('keyless_records')
                ->orderBy('value')
                ->pluck('value')
                ->all(),
        )->toBe(['first', 'second']);
    });

    it('blocks row deletion for a composite primary key', function () {
        createCompositeRecordsTable();

        Livewire::test(RowAddressingSafetyPage::class)
            ->call(
                'deleteRow',
                'composite_records',
                ['tenant_id' => 1, 'code' => 'shared'],
                'testing',
            )
            ->assertForbidden();

        expect(DB::connection('testing')->table('composite_records')->count())
            ->toBe(2);
    });

    it('blocks predicates that do not use the single-column primary key', function () {
        $this->seedTestData();

        Livewire::test(RowAddressingSafetyPage::class)
            ->call(
                'updateRow',
                'users',
                ['email' => 'alice@example.com'],
                ['name' => 'Changed'],
                'testing',
            )
            ->assertForbidden();

        expect(
            DB::connection('testing')
                ->table('users')
                ->where('email', 'alice@example.com')
                ->value('name'),
        )->toBe('Alice');
    });

    it('allows a mutation addressed by a single-column primary key', function () {
        $this->seedTestData();

        Livewire::test(RowAddressingSafetyPage::class)
            ->call('updateRow', 'users', ['id' => 1], ['name' => 'Changed'], 'testing')
            ->assertReturned(1);

        expect(DB::connection('testing')->table('users')->where('id', 1)->value('name'))
            ->toBe('Changed');
    });
});
