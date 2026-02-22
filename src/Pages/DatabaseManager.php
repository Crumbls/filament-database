<?php

namespace Crumbls\FilamentDatabase\Pages;

use BackedEnum;
use Crumbls\FilamentDatabase\Actions\ExportTable;
use Crumbls\FilamentDatabase\Actions\ImportTable;
use Crumbls\FilamentDatabase\Concerns\BuildsFormFields;
use Crumbls\FilamentDatabase\Concerns\InteractsWithDatabase;
use Crumbls\FilamentDatabase\FilamentDatabasePlugin;
use Crumbls\FilamentDatabase\Models\DynamicModel;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

class DatabaseManager extends Page implements HasTable
{
    use BuildsFormFields;
    use InteractsWithDatabase;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = null;
    protected static string|UnitEnum|null $navigationGroup = null;
    protected static ?int $navigationSort = null;
    protected static ?string $title = 'Database';
    protected static ?string $slug = 'database';
    protected string $view = 'filament-database::pages.database-manager';

    // Connection health cache: connection => true|string(error)
    public array $connectionHealth = [];

    // State
    public string $activeConnection = '';
    public string $activeTable = '';
    public string $activeTab = 'rows';
    public string $tableSearch = '';
    public string $sqlQuery = '';
    public array $sqlResults = [];
    public string $sqlError = '';
    public array $sqlHistory = [];

    // Create table
    public bool $showCreateTable = false;
    public string $newTableName = '';
    public array $newTableColumns = [];

    // Add column
    public bool $showAddColumn = false;
    public string $newColumnName = '';
    public string $newColumnType = 'string';
    public bool $newColumnNullable = false;
    public string $newColumnDefault = '';

    // Legacy — kept for backwards compat but modals are now native Filament actions

    // ─── Navigation (from plugin config) ───────────────────────

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return static::getPlugin()->getNavigationIcon()
            ?? config('filament-database.navigation.icon', 'heroicon-o-circle-stack');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::getPlugin()->getNavigationGroup()
            ?? config('filament-database.navigation.group', 'System');
    }

    public static function getNavigationSort(): ?int
    {
        return static::getPlugin()->getNavigationSort()
            ?? config('filament-database.navigation.sort', 100);
    }

    public static function getNavigationLabel(): string
    {
        return static::getPlugin()->getNavigationLabel() ?? 'Database';
    }

    protected static function getPlugin(): FilamentDatabasePlugin
    {
        return FilamentDatabasePlugin::get();
    }

    // ─── Access Control ────────────────────────────────────────

    public static function canAccess(): bool
    {
        return static::getPlugin()->canAccess();
    }

    // ─── Lifecycle ─────────────────────────────────────────────

    public function mount(): void
    {
        $plugin = static::getPlugin();

        // Test all connections and cache health
        $this->connectionHealth = [];
        foreach ($plugin->getAllowedConnections() as $conn) {
            $this->connectionHealth[$conn] = $plugin->testConnection($conn);
        }

        // Pick default connection (must be healthy)
        $default = $plugin->getDefaultConnection();
        if (isset($this->connectionHealth[$default]) && $this->connectionHealth[$default] === true) {
            $this->activeConnection = $default;
        } else {
            // First healthy connection
            $this->activeConnection = '';
            foreach ($this->connectionHealth as $conn => $health) {
                if ($health === true) {
                    $this->activeConnection = $conn;
                    break;
                }
            }
        }
    }

    public function getAvailableConnections(): array
    {
        return static::getPlugin()->getAllowedConnections();
    }

    public function getHealthyConnections(): array
    {
        return array_keys(array_filter($this->connectionHealth, fn($h) => $h === true));
    }

    public function isConnectionHealthy(string $connection): bool
    {
        return ($this->connectionHealth[$connection] ?? false) === true;
    }

    public function getConnectionError(string $connection): ?string
    {
        $health = $this->connectionHealth[$connection] ?? null;
        return $health === true ? null : ($health ?? 'Unknown error');
    }

    public function switchConnection(string $connection): void
    {
        if (!$this->isConnectionHealthy($connection)) {
            Notification::make()
                ->title("Connection '{$connection}' is not available")
                ->body($this->getConnectionError($connection))
                ->danger()
                ->send();
            return;
        }

        $this->activeConnection = $connection;
        $this->activeTable = '';
        $this->resetTable();
    }

    public function selectTable(string $table): void
    {
        $plugin = static::getPlugin();

        if (!$plugin->isTableVisible($table)) {
            Notification::make()->title('Access denied.')->danger()->send();
            return;
        }

        $this->activeTable = $table;
        $this->activeTab = 'rows';
        $this->resetTable();
    }

    public function switchTab(string $tab): void
    {
        $plugin = static::getPlugin();

        if ($tab === 'sql' && !$plugin->isQueryRunnerEnabled()) {
            Notification::make()->title('SQL runner is disabled.')->warning()->send();
            return;
        }

        $this->activeTab = $tab;
    }

    /**
     * Get database overview statistics.
     */
    public function getDatabaseOverview(?string $connection = null): array
    {
        $conn = $connection ?? $this->activeConnection;
        $tables = $this->getFilteredTables($conn);
        
        $totalTables = count($tables);
        $totalRows = 0;
        $largestTables = [];
        
        foreach ($tables as $table) {
            $rowCount = $table['row_count'] ?? 0;
            $totalRows += $rowCount;
            $largestTables[] = [
                'name' => $table['name'],
                'rows' => $rowCount,
            ];
        }
        
        // Sort by row count descending and take top 10
        usort($largestTables, fn($a, $b) => $b['rows'] <=> $a['rows']);
        $largestTables = array_slice($largestTables, 0, 10);
        
        $driver = $this->getDriverName($conn);
        $database = DB::connection($conn)->getDatabaseName();
        
        return [
            'total_tables' => $totalTables,
            'total_rows' => $totalRows,
            'largest_tables' => $largestTables,
            'driver' => $driver,
            'database' => $database,
        ];
    }

    /**
     * Get filtered table list respecting hideTables/showOnlyTables with row counts.
     */
    public function getFilteredTables(?string $connection = null): array
    {
        $plugin = static::getPlugin();
        $tables = $this->getTables($connection);
        $tables = array_filter($tables, fn($t) => $plugin->isTableVisible($t['name']));

        // Add row counts efficiently
        $conn = $connection ?? $this->activeConnection;
        $driver = $this->getDriverName($conn);

        try {
            if ($driver === 'mysql') {
                // Use information_schema for MySQL (fast, uses cached stats)
                $database = DB::connection($conn)->getDatabaseName();
                $counts = DB::connection($conn)
                    ->table('information_schema.TABLES')
                    ->where('TABLE_SCHEMA', $database)
                    ->whereIn('TABLE_NAME', array_column($tables, 'name'))
                    ->pluck('TABLE_ROWS', 'TABLE_NAME')
                    ->toArray();

                foreach ($tables as &$table) {
                    $table['row_count'] = $counts[$table['name']] ?? 0;
                }
            } else {
                // For SQLite/PostgreSQL, do individual COUNT queries (cached per request)
                static $rowCounts = [];

                foreach ($tables as &$table) {
                    $tableName = $table['name'];
                    if (!isset($rowCounts[$conn][$tableName])) {
                        $rowCounts[$conn][$tableName] = DB::connection($conn)
                            ->table($tableName)
                            ->count();
                    }
                    $table['row_count'] = $rowCounts[$conn][$tableName];
                }
            }
        } catch (\Throwable) {
            // If row count fails, just skip it
            foreach ($tables as &$table) {
                $table['row_count'] = null;
            }
        }

        return $tables;
    }

    // ─── Filament Table ────────────────────────────────────────

    protected function getTableQuery(): ?Builder
    {
        if (!$this->activeTable || !$this->activeConnection) {
            // Return empty query
            $fallbackConn = $this->activeConnection ?: config('database.default');
            return DynamicModel::forTable('__nonexistent__', $fallbackConn)->newQuery()->whereRaw('1 = 0');
        }

        $primaryKey = $this->detectPrimaryKey($this->activeTable, $this->activeConnection);
        return DynamicModel::forTable($this->activeTable, $this->activeConnection, $primaryKey)->newQuery();
    }

    public function table(Table $table): Table
    {
        $plugin = static::getPlugin();
        $columns = [];

        if ($this->activeTable && $this->activeConnection) {
            $dbColumns = $this->getColumns($this->activeTable, $this->activeConnection);

            foreach ($dbColumns as $col) {
                $columns[] = TextColumn::make($col['name'])
                    ->label($col['name'])
                    ->limit(50)
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copied!')
                    ->copyMessageDuration(1500);
            }
        }

        $actions = [];
        $headerActions = [];

        // Export action (always available, even in read-only mode)
        if ($this->activeTable) {
            $headerActions[] = Action::make('export')
                ->label('Export')
                ->icon('heroicon-m-arrow-down-tray')
                ->form([
                    Components\Select::make('format')
                        ->label('Format')
                        ->options([
                            'csv' => 'CSV',
                            'json' => 'JSON',
                            'sql' => 'SQL INSERT',
                        ])
                        ->default('csv')
                        ->required(),
                    Components\Select::make('scope')
                        ->label('Rows')
                        ->options([
                            'current' => 'Current page only',
                            'all' => 'All rows',
                        ])
                        ->default('current')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $allRows = $data['scope'] === 'all';
                    $currentPageData = $allRows ? null : $this->getTableRecords()->map(fn($r) => $r->getAttributes())->toArray();

                    return ExportTable::make(
                        $this->activeTable,
                        $this->activeConnection,
                        $data['format'],
                        $allRows,
                        $currentPageData
                    )->download();
                });
        }

        // Import action (blocked in read-only mode)
        if (!$plugin->isReadOnly() && $this->activeTable) {
            $headerActions[] = Action::make('import')
                ->label('Import')
                ->icon('heroicon-m-arrow-up-tray')
                ->form([
                    Components\FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->required()
                        ->helperText('CSV file with headers. Columns will be auto-matched to table columns.'),
                ])
                ->modalWidth('2xl')
                ->action(function (array $data) use ($plugin) {
                    try {
                        $uploadedFile = $data['csv_file'];
                        $filePath = $uploadedFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
                            ? $uploadedFile->getRealPath()
                            : storage_path('app/public/' . $uploadedFile);

                        $importer = ImportTable::make($this->activeTable, $this->activeConnection);
                        $preview = $importer->parseCsv($filePath);
                        $columnMapping = $importer->autoMapColumns();

                        $result = $importer->import($columnMapping);

                        if ($plugin->shouldLogChanges()) {
                            Log::info('[filament-database] Data imported', [
                                'user' => auth()->id(),
                                'connection' => $this->activeConnection,
                                'table' => $this->activeTable,
                                'success' => $result['success'],
                                'errors' => $result['errors'],
                            ]);
                        }

                        $message = "Imported {$result['success']} row(s) successfully";
                        if ($result['errors'] > 0) {
                            $message .= " ({$result['errors']} error(s))";
                        }

                        Notification::make()
                            ->title($message)
                            ->body($result['errors'] > 0 ? implode("\n", array_slice($result['error_messages'], 0, 3)) : null)
                            ->success($result['errors'] === 0)
                            ->warning($result['errors'] > 0)
                            ->persistent()
                            ->send();

                        $this->resetTable();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                });
        }

        if (!$plugin->isReadOnly() && $this->activeTable) {
            $dbColumns = $this->getColumns($this->activeTable, $this->activeConnection);
            $foreignKeys = $this->getForeignKeys($this->activeTable, $this->activeConnection);

            $actions[] = Action::make('edit')
                ->icon('heroicon-m-pencil-square')
                ->modalHeading('Edit Row')
                ->form($this->buildFormFields($dbColumns, $foreignKeys, $this->activeConnection, false))
                ->fillForm(fn($record) => $record->getAttributes())
                ->action(function (array $data, $record) use ($plugin) {
                    try {
                        $data = $this->processNullCheckboxes($data);
                        $this->updateRow($this->activeTable, $record->getAttributes(), $data, $this->activeConnection);

                        if ($plugin->shouldLogChanges()) {
                            Log::info('[filament-database] Row updated', [
                                'user' => auth()->id(),
                                'connection' => $this->activeConnection,
                                'table' => $this->activeTable,
                                'from' => $record->getAttributes(),
                                'to' => $data,
                            ]);
                        }

                        Notification::make()->title('Row updated.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                });

            if (!$plugin->isDestructivePrevented()) {
                $actions[] = Action::make('delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete row')
                    ->modalDescription('Are you sure you want to delete this row? This cannot be undone.')
                    ->action(function ($record) use ($plugin) {
                        try {
                            $this->deleteRow($this->activeTable, $record->getAttributes(), $this->activeConnection);

                            if ($plugin->shouldLogChanges()) {
                                Log::info('[filament-database] Row deleted', [
                                    'user' => auth()->id(),
                                    'connection' => $this->activeConnection,
                                    'table' => $this->activeTable,
                                    'row' => $record->getAttributes(),
                                ]);
                            }

                            Notification::make()->title('Row deleted.')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    });
            }

            $headerActions[] = Action::make('insert')
                ->label('Insert Row')
                ->icon('heroicon-m-plus')
                ->modalHeading("Insert into '{$this->activeTable}'")
                ->form($this->buildFormFields($dbColumns, $foreignKeys, $this->activeConnection, true))
                ->action(function (array $data) use ($plugin) {
                    try {
                        // Process NULL checkboxes
                        $data = $this->processNullCheckboxes($data);
                        // Filter out empty values but keep explicit NULLs
                        $data = array_filter($data, fn($v) => $v !== '' || $v === null, ARRAY_FILTER_USE_BOTH);
                        $this->insertRow($this->activeTable, $data, $this->activeConnection);

                        if ($plugin->shouldLogChanges()) {
                            Log::info('[filament-database] Row inserted', [
                                'user' => auth()->id(),
                                'connection' => $this->activeConnection,
                                'table' => $this->activeTable,
                                'data' => $data,
                            ]);
                        }

                        Notification::make()->title('Row inserted.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                });
        }

        // Table-level destructive actions in header
        if (!$plugin->isReadOnly() && !$plugin->isDestructivePrevented() && $this->activeTable) {
            $headerActions[] = Action::make('truncate')
                ->label('Truncate')
                ->icon('heroicon-m-archive-box-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading("Truncate '{$this->activeTable}'")
                ->modalDescription('This will permanently remove all rows from the table. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, truncate')
                ->action(function () use ($plugin) {
                    try {
                        $this->truncateTable($this->activeTable, $this->activeConnection);

                        if ($plugin->shouldLogChanges()) {
                            Log::warning('[filament-database] Table truncated', [
                                'user' => auth()->id(),
                                'connection' => $this->activeConnection,
                                'table' => $this->activeTable,
                            ]);
                        }

                        Notification::make()->title("Table '{$this->activeTable}' truncated.")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                });

            $headerActions[] = Action::make('drop')
                ->label('Drop')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading("Drop '{$this->activeTable}'")
                ->modalDescription('This will permanently delete the entire table and all its data. This cannot be undone.')
                ->modalSubmitActionLabel('Yes, drop table')
                ->action(function () use ($plugin) {
                    try {
                        $table = $this->activeTable;
                        $this->dropTable($table, $this->activeConnection);
                        $this->activeTable = '';
                        $this->resetTable();

                        if ($plugin->shouldLogChanges()) {
                            Log::warning('[filament-database] Table dropped', [
                                'user' => auth()->id(),
                                'connection' => $this->activeConnection,
                                'table' => $table,
                            ]);
                        }

                        Notification::make()->title("Table '{$table}' dropped.")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                    }
                });
        }

        $perPage = $plugin->getRowsPerPage();
        $maxPerPage = $plugin->getMaxRowsPerPage();
        $pageOptions = array_unique(array_filter([10, 25, 50, 100, $perPage], fn($n) => $n <= $maxPerPage));
        sort($pageOptions);

        return $table
            ->query(fn() => $this->getTableQuery())
            ->columns($columns)
            ->defaultPaginationPageOption($perPage)
            ->paginationPageOptions($pageOptions)
            ->actions($actions)
            ->headerActions($headerActions)
            ->emptyStateHeading('No rows')
            ->emptyStateDescription('This table has no data.')
            ->striped()
            ->paginated();
    }

    protected function detectPrimaryKey(string $tableName, string $connection): ?string
    {
        $indexes = $this->getIndexes($tableName, $connection);
        foreach ($indexes as $idx) {
            if (($idx['primary'] ?? false) || str_contains(strtolower($idx['name'] ?? ''), 'primary')) {
                $cols = $idx['columns'] ?? [];
                if (count($cols) === 1) {
                    return $cols[0];
                }
            }
        }

        $columns = $this->getColumns($tableName, $connection);
        foreach ($columns as $col) {
            if ($col['name'] === 'id') return 'id';
        }

        return $columns[0]['name'] ?? null;
    }

    /**
     * Process form data to handle __null checkboxes for nullable fields.
     */
    protected function processNullCheckboxes(array $data): array
    {
        $processed = [];

        foreach ($data as $key => $value) {
            // Skip __null checkbox fields
            if (str_ends_with($key, '__null')) {
                continue;
            }

            // Check if this field has a corresponding __null checkbox that's checked
            $nullKey = "{$key}__null";
            if (isset($data[$nullKey]) && $data[$nullKey] === true) {
                $processed[$key] = null;
            } else {
                $processed[$key] = $value;
            }
        }

        return $processed;
    }

    // ─── Table Operations ──────────────────────────────────────

    public function confirmDropTable(string $table): void
    {
        $plugin = static::getPlugin();

        if ($plugin->isReadOnly() || $plugin->isDestructivePrevented()) {
            Notification::make()->title('Destructive operations are disabled.')->danger()->send();
            return;
        }

        try {
            $this->dropTable($table, $this->activeConnection);
            if ($this->activeTable === $table) {
                $this->activeTable = '';
            }

            if ($plugin->shouldLogChanges()) {
                Log::warning('[filament-database] Table dropped', [
                    'user' => auth()->id(),
                    'connection' => $this->activeConnection,
                    'table' => $table,
                ]);
            }

            Notification::make()->title("Table '{$table}' dropped.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function confirmTruncateTable(string $table): void
    {
        $plugin = static::getPlugin();

        if ($plugin->isReadOnly() || $plugin->isDestructivePrevented()) {
            Notification::make()->title('Destructive operations are disabled.')->danger()->send();
            return;
        }

        try {
            $this->truncateTable($table, $this->activeConnection);

            if ($plugin->shouldLogChanges()) {
                Log::warning('[filament-database] Table truncated', [
                    'user' => auth()->id(),
                    'connection' => $this->activeConnection,
                    'table' => $table,
                ]);
            }

            Notification::make()->title("Table '{$table}' truncated.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // ─── Column Operations ─────────────────────────────────────

    public function openAddColumn(): void
    {
        $this->newColumnName = '';
        $this->newColumnType = 'string';
        $this->newColumnNullable = false;
        $this->newColumnDefault = '';
        $this->showAddColumn = true;
    }

    public function saveAddColumn(): void
    {
        $plugin = static::getPlugin();

        if ($plugin->isReadOnly()) {
            Notification::make()->title('Read-only mode.')->danger()->send();
            return;
        }

        try {
            $options = ['nullable' => $this->newColumnNullable];
            if ($this->newColumnDefault !== '') {
                $options['default'] = $this->newColumnDefault;
            }

            $this->addColumn($this->activeTable, $this->newColumnName, $this->newColumnType, $options, $this->activeConnection);
            $this->showAddColumn = false;

            if ($plugin->shouldLogChanges()) {
                Log::info('[filament-database] Column added', [
                    'user' => auth()->id(),
                    'connection' => $this->activeConnection,
                    'table' => $this->activeTable,
                    'column' => $this->newColumnName,
                    'type' => $this->newColumnType,
                ]);
            }

            Notification::make()->title("Column '{$this->newColumnName}' added.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function confirmDropColumn(string $column): void
    {
        $plugin = static::getPlugin();

        if ($plugin->isReadOnly() || $plugin->isDestructivePrevented()) {
            Notification::make()->title('Destructive operations are disabled.')->danger()->send();
            return;
        }

        try {
            $this->dropColumn($this->activeTable, $column, $this->activeConnection);

            if ($plugin->shouldLogChanges()) {
                Log::warning('[filament-database] Column dropped', [
                    'user' => auth()->id(),
                    'connection' => $this->activeConnection,
                    'table' => $this->activeTable,
                    'column' => $column,
                ]);
            }

            Notification::make()->title("Column '{$column}' dropped.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    public function getEditColumnAction(string $columnName): Action
    {
        $plugin = static::getPlugin();
        $columns = $this->getColumns($this->activeTable, $this->activeConnection);
        $column = collect($columns)->firstWhere('name', $columnName);

        if (!$column) {
            throw new \RuntimeException("Column '{$columnName}' not found");
        }

        return Action::make('editColumn_' . $columnName)
            ->label('Edit')
            ->icon('heroicon-m-pencil')
            ->modalHeading("Edit Column: {$columnName}")
            ->modalWidth('md')
            ->form([
                Components\TextInput::make('name')
                    ->label('Name')
                    ->default($columnName)
                    ->required(),
                Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'string' => 'String',
                        'text' => 'Text',
                        'integer' => 'Integer',
                        'bigInteger' => 'Big Integer',
                        'decimal' => 'Decimal',
                        'boolean' => 'Boolean',
                        'date' => 'Date',
                        'dateTime' => 'DateTime',
                        'time' => 'Time',
                        'json' => 'JSON',
                    ])
                    ->default($this->mapDbTypeToSchemaType($column['type_name'] ?? $column['type']))
                    ->required(),
                Components\Toggle::make('nullable')
                    ->label('Nullable')
                    ->default($column['nullable'] ?? false),
                Components\TextInput::make('default')
                    ->label('Default Value')
                    ->default($column['default'] ?? ''),
            ])
            ->action(function (array $data) use ($columnName, $plugin) {
                try {
                    // Rename column if name changed
                    if ($data['name'] !== $columnName) {
                        $this->renameColumn($this->activeTable, $columnName, $data['name'], $this->activeConnection);
                    }

                    // Modify column properties
                    $options = [
                        'nullable' => $data['nullable'],
                        'default' => $data['default'],
                        'arguments' => [],
                    ];

                    $this->modifyColumn($this->activeTable, $data['name'], $data['type'], $options, $this->activeConnection);

                    if ($plugin->shouldLogChanges()) {
                        Log::info('[filament-database] Column modified', [
                            'user' => auth()->id(),
                            'connection' => $this->activeConnection,
                            'table' => $this->activeTable,
                            'from' => $columnName,
                            'to' => $data,
                        ]);
                    }

                    Notification::make()->title("Column updated.")->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                }
            });
    }

    protected function mapDbTypeToSchemaType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match (true) {
            str_contains($dbType, 'int') && str_contains($dbType, 'big') => 'bigInteger',
            str_contains($dbType, 'int') => 'integer',
            str_contains($dbType, 'varchar') || str_contains($dbType, 'char') => 'string',
            str_contains($dbType, 'text') => 'text',
            str_contains($dbType, 'decimal') || str_contains($dbType, 'numeric') => 'decimal',
            str_contains($dbType, 'bool') => 'boolean',
            str_contains($dbType, 'date') && !str_contains($dbType, 'time') => 'date',
            str_contains($dbType, 'datetime') || str_contains($dbType, 'timestamp') => 'dateTime',
            str_contains($dbType, 'time') => 'time',
            str_contains($dbType, 'json') => 'json',
            default => 'string',
        };
    }

    // ─── Create Table ──────────────────────────────────────────

    public function openCreateTable(): void
    {
        $this->newTableName = '';
        $this->newTableColumns = [
            ['name' => 'id', 'type' => 'id', 'nullable' => false, 'default' => '', 'primary' => true, 'autoIncrement' => true, 'arguments' => []],
        ];
        $this->showCreateTable = true;
    }

    public function addNewTableColumn(): void
    {
        $this->newTableColumns[] = ['name' => '', 'type' => 'string', 'nullable' => false, 'default' => '', 'primary' => false, 'autoIncrement' => false, 'arguments' => []];
    }

    public function removeNewTableColumn(int $index): void
    {
        unset($this->newTableColumns[$index]);
        $this->newTableColumns = array_values($this->newTableColumns);
    }

    public function saveCreateTable(): void
    {
        $plugin = static::getPlugin();

        if ($plugin->isReadOnly()) {
            Notification::make()->title('Read-only mode.')->danger()->send();
            return;
        }

        try {
            $this->createTable($this->newTableName, $this->newTableColumns, $this->activeConnection);
            $this->showCreateTable = false;
            $this->activeTable = $this->newTableName;

            if ($plugin->shouldLogChanges()) {
                Log::info('[filament-database] Table created', [
                    'user' => auth()->id(),
                    'connection' => $this->activeConnection,
                    'table' => $this->newTableName,
                ]);
            }

            Notification::make()->title("Table '{$this->newTableName}' created.")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
        }
    }

    // ─── SQL Runner ────────────────────────────────────────────

    public function executeSql(): void
    {
        $plugin = static::getPlugin();
        $this->sqlResults = [];
        $this->sqlError = '';

        if (!$plugin->isQueryRunnerEnabled()) {
            $this->sqlError = 'SQL runner is disabled.';
            return;
        }

        $upper = strtoupper(trim(substr($this->sqlQuery, 0, 6)));
        $isRead = in_array($upper, ['SELECT', 'SHOW  ', 'DESCRI', 'EXPLAI']);

        if ($plugin->isQueryRunnerReadOnly() && !$isRead) {
            $this->sqlError = 'SQL runner is in read-only mode: only SELECT queries are allowed.';
            return;
        }

        if ($plugin->isReadOnly() && !$isRead) {
            $this->sqlError = 'Read-only mode: only SELECT queries are allowed.';
            return;
        }

        if ($plugin->shouldLogQueries()) {
            Log::info('[filament-database] SQL executed', [
                'user' => auth()->id(),
                'connection' => $this->activeConnection,
                'query' => $this->sqlQuery,
            ]);
        }

        try {
            $startTime = microtime(true);
            $results = $this->runQuery($this->sqlQuery, $this->activeConnection);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->sqlResults = array_map(fn($r) => (array) $r, $results);

            // Add to history (keep last 20)
            array_unshift($this->sqlHistory, [
                'query' => $this->sqlQuery,
                'time' => now()->format('H:i:s'),
                'rows' => count($this->sqlResults),
                'duration' => $executionTime . 'ms',
            ]);
            $this->sqlHistory = array_slice($this->sqlHistory, 0, 20);
        } catch (\Throwable $e) {
            $this->sqlError = $e->getMessage();

            // Add failed query to history
            array_unshift($this->sqlHistory, [
                'query' => $this->sqlQuery,
                'time' => now()->format('H:i:s'),
                'rows' => 0,
                'duration' => 'error',
            ]);
            $this->sqlHistory = array_slice($this->sqlHistory, 0, 20);
        }
    }

    public function loadHistoryQuery(int $index): void
    {
        if (isset($this->sqlHistory[$index])) {
            $this->sqlQuery = $this->sqlHistory[$index]['query'];
        }
    }

    // ─── Helpers ───────────────────────────────────────────────

    public function isReadOnly(): bool
    {
        return static::getPlugin()->isReadOnly();
    }

    public function isDestructivePrevented(): bool
    {
        return static::getPlugin()->isDestructivePrevented();
    }

    public function isQueryRunnerEnabled(): bool
    {
        return static::getPlugin()->isQueryRunnerEnabled();
    }

    public function getColumnTypes(): array
    {
        return [
            'id', 'bigIncrements', 'bigInteger', 'binary', 'boolean',
            'char', 'date', 'dateTime', 'decimal', 'double',
            'enum', 'float', 'increments', 'integer', 'json',
            'jsonb', 'longText', 'mediumInteger', 'mediumText',
            'smallInteger', 'string', 'text', 'time', 'timestamp',
            'tinyInteger', 'unsignedBigInteger', 'unsignedInteger',
            'uuid',
        ];
    }
}
