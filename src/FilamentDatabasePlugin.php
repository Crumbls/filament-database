<?php

namespace Crumbls\FilamentDatabase;

use Closure;
use Crumbls\FilamentDatabase\Pages\DatabaseManager;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;

class FilamentDatabasePlugin implements Plugin
{
    // ─── Access Control ────────────────────────────────────────
    protected ?Closure $authorizeUsing = null;
    protected ?string $authorizeGate = null;
    protected ?array $allowedEmails = null;

    // ─── Connection Control ────────────────────────────────────
    protected ?array $connections = null;
    protected array $excludedConnections = [];
    protected ?string $defaultConnection = null;

    // ─── Safety ────────────────────────────────────────────────
    protected bool $readOnly = false;
    protected bool $preventDestructive = false;
    protected bool $requireConfirmation = false;
    protected array $hiddenTables = [];
    protected ?array $visibleTables = null;

    // ─── SQL Runner ────────────────────────────────────────────
    protected bool $queryRunnerEnabled = true;
    protected bool $queryRunnerReadOnly = false;

    // ─── UI/UX ─────────────────────────────────────────────────
    protected ?string $navigationGroup = null;
    protected ?string $navigationIcon = null;
    protected ?int $navigationSort = null;
    protected ?string $navigationLabel = null;
    protected int $rowsPerPage = 25;
    protected int $maxRowsPerPage = 500;

    // ─── Audit ─────────────────────────────────────────────────
    protected bool $logQueries = false;
    protected bool $logChanges = false;

    // ════════════════════════════════════════════════════════════
    // Factory
    // ════════════════════════════════════════════════════════════

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-database';
    }

    // ════════════════════════════════════════════════════════════
    // Access Control
    // ════════════════════════════════════════════════════════════

    public function authorize(Closure $callback): static
    {
        $this->authorizeUsing = $callback;
        return $this;
    }

    public function authorizeUsing(string $gate): static
    {
        $this->authorizeGate = $gate;
        return $this;
    }

    public function onlyForEmails(array $emails): static
    {
        $this->allowedEmails = array_map('strtolower', $emails);
        return $this;
    }

    public function canAccess(): bool
    {
        $user = auth()->user();

        if ($this->authorizeUsing !== null) {
            return ($this->authorizeUsing)($user);
        }

        if ($this->authorizeGate !== null) {
            return Gate::allows($this->authorizeGate);
        }

        if ($this->allowedEmails !== null) {
            return $user && in_array(strtolower($user->email ?? ''), $this->allowedEmails);
        }

        return true;
    }

    // ════════════════════════════════════════════════════════════
    // Connection Control
    // ════════════════════════════════════════════════════════════

    public function connections(?array $connections): static
    {
        $this->connections = $connections;
        return $this;
    }

    public function excludeConnections(array $connections): static
    {
        $this->excludedConnections = $connections;
        return $this;
    }

    public function defaultConnection(string $connection): static
    {
        $this->defaultConnection = $connection;
        return $this;
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection ?? config('database.default');
    }

    public function getAllowedConnections(): array
    {
        $all = array_keys(config('database.connections', []));
        $allowed = $this->connections ?? config('filament-database.connections') ?? $all;

        if (!is_array($allowed)) {
            $allowed = $all;
        }

        $allowed = array_intersect($all, $allowed);
        $allowed = array_diff($allowed, $this->excludedConnections);

        return array_values($allowed);
    }

    public function isConnectionValid(string $connection): bool
    {
        return in_array($connection, $this->getAllowedConnections());
    }

    /**
     * Test if a connection is reachable. Returns true or an error message.
     */
    public function testConnection(string $connection): true|string
    {
        try {
            \Illuminate\Support\Facades\DB::connection($connection)->getPdo();
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    // ════════════════════════════════════════════════════════════
    // Safety
    // ════════════════════════════════════════════════════════════

    public function readOnly(bool $readOnly = true): static
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function preventDestructive(bool $prevent = true): static
    {
        $this->preventDestructive = $prevent;
        return $this;
    }

    public function isDestructivePrevented(): bool
    {
        return $this->preventDestructive;
    }

    public function requireConfirmation(bool $require = true): static
    {
        $this->requireConfirmation = $require;
        return $this;
    }

    public function isConfirmationRequired(): bool
    {
        return $this->requireConfirmation;
    }

    public function hideTables(array $tables): static
    {
        $this->hiddenTables = $tables;
        return $this;
    }

    public function getHiddenTables(): array
    {
        return $this->hiddenTables;
    }

    public function showOnlyTables(array $tables): static
    {
        $this->visibleTables = $tables;
        return $this;
    }

    public function getVisibleTables(): ?array
    {
        return $this->visibleTables;
    }

    public function isTableVisible(string $table): bool
    {
        if ($this->visibleTables !== null) {
            return in_array($table, $this->visibleTables);
        }

        return !in_array($table, $this->hiddenTables);
    }

    // ════════════════════════════════════════════════════════════
    // SQL Runner
    // ════════════════════════════════════════════════════════════

    public function disableQueryRunner(bool $disable = true): static
    {
        $this->queryRunnerEnabled = !$disable;
        return $this;
    }

    public function isQueryRunnerEnabled(): bool
    {
        if ($this->readOnly && !$this->queryRunnerReadOnly) {
            // In read-only mode, runner is still enabled but restricted
            return $this->queryRunnerEnabled;
        }
        return $this->queryRunnerEnabled;
    }

    public function queryRunnerReadOnly(bool $readOnly = true): static
    {
        $this->queryRunnerReadOnly = $readOnly;
        return $this;
    }

    public function isQueryRunnerReadOnly(): bool
    {
        return $this->queryRunnerReadOnly || $this->readOnly;
    }

    // ════════════════════════════════════════════════════════════
    // UI/UX
    // ════════════════════════════════════════════════════════════

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;
        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationIcon(string $icon): static
    {
        $this->navigationIcon = $icon;
        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        return $this->navigationIcon;
    }

    public function navigationSort(int $sort): static
    {
        $this->navigationSort = $sort;
        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function navigationLabel(string $label): static
    {
        $this->navigationLabel = $label;
        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function rowsPerPage(int $perPage): static
    {
        $this->rowsPerPage = $perPage;
        return $this;
    }

    public function getRowsPerPage(): int
    {
        return $this->rowsPerPage;
    }

    public function maxRowsPerPage(int $max): static
    {
        $this->maxRowsPerPage = $max;
        return $this;
    }

    public function getMaxRowsPerPage(): int
    {
        return $this->maxRowsPerPage;
    }

    // ════════════════════════════════════════════════════════════
    // Audit
    // ════════════════════════════════════════════════════════════

    public function logQueries(bool $log = true): static
    {
        $this->logQueries = $log;
        return $this;
    }

    public function shouldLogQueries(): bool
    {
        return $this->logQueries;
    }

    public function logChanges(bool $log = true): static
    {
        $this->logChanges = $log;
        return $this;
    }

    public function shouldLogChanges(): bool
    {
        return $this->logChanges;
    }

    // ════════════════════════════════════════════════════════════
    // Plugin Lifecycle
    // ════════════════════════════════════════════════════════════

    public function register(Panel $panel): void
    {
        $panel->pages([
            DatabaseManager::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
