<?php

declare(strict_types=1);

use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MigrationDownloadSafetyPage extends DatabaseManager
{
    public function bootedInteractsWithTable(): void
    {
        // These tests do not render a Filament table.
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

function registerMigrationDownloadSafetyPanel(): void
{
    $plugin = (new FilamentDatabasePlugin())
        ->authorize(fn (): bool => true)
        ->connections(['testing'])
        ->readOnly();

    $panel = Panel::make()
        ->id('migration-download-safety')
        ->default()
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
}

describe('Migration download safety', function () {
    beforeEach(function () {
        app()->instance(DataStore::class, new DataStore());
        registerMigrationDownloadSafetyPanel();
    });

    it('locks generated migration state against client replacement', function (string $property, mixed $value) {
        expect(
            fn () => Livewire::test(MigrationDownloadSafetyPage::class)
                ->set($property, $value),
        )->toThrow(CannotUpdateLockedPropertyException::class);
    })->with([
        'generated source' => ['generatedMigration', '<?php phpinfo();'],
        'schema diff' => ['schemaDiff', [
            'tables_added' => ['compromised'],
            'tables_removed' => [],
            'tables_modified' => [],
        ]],
    ]);

    it('downloads freshly generated code without writing application source', function () {
        $component = Livewire::test(MigrationDownloadSafetyPage::class);
        $component->instance()->schemaDiff = [
            'tables_added' => ['products'],
            'tables_removed' => [],
            'tables_modified' => [],
        ];
        $component->instance()->generatedMigration = '<?php phpinfo();';

        $migrationPath = database_path('migrations');
        $filesBefore = is_dir($migrationPath) ? glob($migrationPath . '/*') : [];

        $response = $component->instance()->downloadMigrationFile();

        ob_start();
        ($response->getCallback())();
        $content = (string) ob_get_clean();

        $filesAfter = is_dir($migrationPath) ? glob($migrationPath . '/*') : [];

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and($response->headers->get('content-type'))->toBe('text/x-php')
            ->and($response->headers->get('content-disposition'))
            ->toContain('_schema_changes.php')
            ->and($content)->toContain('declare(strict_types=1);')
            ->and($content)->toContain('products')
            ->and($content)->not->toContain('phpinfo')
            ->and($filesAfter)->toBe($filesBefore);
    });
});
