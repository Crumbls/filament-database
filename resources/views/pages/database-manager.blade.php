<x-filament-panels::page>
    @script
    <script>
        // Copy to clipboard handler
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('copy-to-clipboard', (event) => {
                const text = event.text || event[0]?.text || '';
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text);
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                    } catch (err) {
                        console.error('Failed to copy', err);
                    }
                    document.body.removeChild(textArea);
                }
            });
        });
    </script>
    @endscript
    <style>
        .fdb-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .fdb-toolbar-field { width: 16rem; }
        .fdb-toolbar-field label { display: block; margin-bottom: 0.25rem; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
        .fdb-toolbar-spacer { margin-left: auto; display: flex; gap: 0.5rem; }
        .fdb-status { font-size: 0.75rem; align-self: center; }
        .fdb-status-ok { color: #16a34a; }
        .fdb-status-err { color: #dc2626; }
        .fdb-tab-bar { display: flex; align-items: center; gap: 1rem; }
        .fdb-tab-bar > :first-child { flex: 1; min-width: 0; }
        .fdb-tab-actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
        .fdb-content { margin-top: 1rem; }
        .fdb-content .fi-ta { overflow-x: auto; }
        .fdb-content .fi-ta-content { min-width: max-content; }
        .fdb-empty { display: flex; align-items: center; justify-content: center; height: 16rem; color: var(--gray-400); text-align: center; }
        .fdb-empty svg { width: 3rem; height: 3rem; margin: 0 auto 0.5rem; }

        /* Overview Dashboard */
        .fdb-overview { padding: 1rem 0; }
        .fdb-overview-header { margin-bottom: 1.5rem; }
        .fdb-overview-header h2 { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem; }
        .dark .fdb-overview-header h2 { color: var(--gray-100); }
        .fdb-overview-connection { font-size: 0.875rem; color: var(--gray-500); }
        .fdb-overview-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .fdb-stat-card { padding: 1.25rem; border-radius: 0.5rem; background: var(--gray-50); border: 1px solid var(--gray-200); }
        .dark .fdb-stat-card { background: var(--gray-800); border-color: var(--gray-700); }
        .fdb-stat-value { font-size: 2rem; font-weight: 700; color: var(--primary-600); margin-bottom: 0.25rem; }
        .dark .fdb-stat-value { color: var(--primary-400); }
        .fdb-stat-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
        .fdb-overview-section { margin-bottom: 2rem; }
        .fdb-overview-section h3 { font-size: 1rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.75rem; }
        .dark .fdb-overview-section h3 { color: var(--gray-100); }

        /* Data tables for structure/indexes/fkeys */
        .fdb-table-wrap { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: 0.5rem; }
        .dark .fdb-table-wrap { border-color: var(--gray-700); }
        .fdb-table { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
        .fdb-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); white-space: nowrap; border-bottom: 1px solid var(--gray-200); }
        .dark .fdb-table th { border-color: var(--gray-700); }
        .fdb-table td { padding: 0.5rem 0.75rem; color: var(--gray-600); }
        .dark .fdb-table td { color: var(--gray-400); }
        .fdb-table td.fdb-bold { font-weight: 600; color: var(--gray-900); }
        .dark .fdb-table td.fdb-bold { color: var(--gray-100); }
        .fdb-table tbody tr { border-top: 1px solid var(--gray-100); }
        .dark .fdb-table tbody tr { border-color: var(--gray-800); }
        .fdb-table tbody tr:hover { background: var(--gray-50); }
        .dark .fdb-table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .fdb-table td.fdb-empty-cell { text-align: center; padding: 1rem; color: var(--gray-400); }
        .fdb-table th.fdb-right, .fdb-table td.fdb-right { text-align: right; }
        .fdb-actions-bar { display: flex; justify-content: flex-end; margin-bottom: 0.75rem; }

        /* SQL */
        .fdb-sql-textarea { display: block; width: 100%; padding: 0.75rem; font-family: ui-monospace, monospace; font-size: 0.875rem; border: 1px solid var(--gray-300); border-radius: 0.5rem; background: var(--gray-50); color: var(--gray-900); resize: vertical; min-height: 5rem; }
        .dark .fdb-sql-textarea { background: var(--gray-800); border-color: var(--gray-600); color: var(--gray-100); }
        .fdb-error-box { margin-top: 1rem; padding: 0.75rem; border-radius: 0.5rem; font-size: 0.875rem; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .dark .fdb-error-box { background: rgba(220,38,38,0.1); border-color: rgba(220,38,38,0.3); color: #f87171; }
        .fdb-result-count { margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-400); }

        /* Modals */
        .fdb-modal-overlay { position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); }
        .fdb-modal { background: white; border-radius: 0.75rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); padding: 1.5rem; max-height: 80vh; overflow-y: auto; }
        .dark .fdb-modal { background: var(--gray-900); }
        .fdb-modal-sm { width: 100%; max-width: 28rem; }
        .fdb-modal-md { width: 100%; max-width: 32rem; }
        .fdb-modal-lg { width: 100%; max-width: 40rem; }
        .fdb-modal h3 { font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--gray-900); }
        .dark .fdb-modal h3 { color: var(--gray-100); }
        .fdb-field { margin-bottom: 0.75rem; }
        .fdb-field label { display: block; font-size: 0.875rem; font-weight: 500; color: var(--gray-700); margin-bottom: 0.25rem; }
        .dark .fdb-field label { color: var(--gray-300); }
        .fdb-modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
        .fdb-col-row { display: flex; gap: 0.5rem; align-items: flex-end; margin-bottom: 0.5rem; }
        .fdb-col-row-name { flex: 1; }
        .fdb-col-row-type { width: 10rem; }
        .fdb-checkbox-label { display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; color: var(--gray-600); }
        .dark .fdb-checkbox-label { color: var(--gray-400); }
    </style>

    {{-- Toolbar --}}
    <div class="fdb-toolbar">
        <div class="fdb-toolbar-field">
            <label>Connection</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:change="switchConnection($event.target.value)">
                    @foreach($this->getAvailableConnections() as $conn)
                        <option value="{{ $conn }}" @selected($conn === $activeConnection) @disabled(!$this->isConnectionHealthy($conn))>
                            {{ $conn }} ({{ $this->isConnectionHealthy($conn) ? $this->getDriverName($conn) : '⚠ offline' }})
                        </option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @if($activeConnection && !$this->isConnectionHealthy($activeConnection))
            <span class="fdb-status fdb-status-err">⚠ {{ $this->getConnectionError($activeConnection) }}</span>
        @endif

        @if($activeConnection && $this->isConnectionHealthy($activeConnection))
            @php
                $allTables = $this->getFilteredTables($activeConnection);
                $filteredTables = $tableFilter
                    ? array_filter($allTables, fn($t) => stripos($t['name'], $tableFilter) !== false)
                    : $allTables;
            @endphp
            <div class="fdb-toolbar-field" style="width: 20rem;">
                <label>Table ({{ count($allTables) }})</label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.300ms="tableFilter"
                        placeholder="Search tables..."
                        style="margin-bottom: 0.25rem;"
                    />
                    <x-filament::input.select wire:change="selectTable($event.target.value)">
                        <option value="">— Select a table —</option>
                        @foreach($filteredTables as $table)
                            @php
                                $displayName = $table['name'];
                                if (isset($table['row_count'])) {
                                    $displayName .= ' (' . number_format($table['row_count']) . ')';
                                }
                            @endphp
                            <option value="{{ $table['name'] }}" @selected($activeTable === $table['name'])>{{ $displayName }}</option>
                        @endforeach
                        @if(count($filteredTables) === 0 && $tableFilter)
                            <option disabled>No tables match "{{ $tableFilter }}"</option>
                        @endif
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if(!$this->isReadOnly())
                <x-filament::button size="sm" wire:click="openCreateTable" icon="heroicon-m-plus">New Table</x-filament::button>
            @endif

{{-- Truncate/Drop moved to table header actions --}}
        @endif

    </div>

    @if($activeTable)
        {{-- Tabs + danger actions --}}
        <x-filament::tabs>
            @php
                $tabs = ['rows' => 'Rows', 'structure' => 'Structure', 'indexes' => 'Indexes', 'foreign-keys' => 'Foreign Keys', 'relationships' => 'Relationships'];
                if ($this->isQueryRunnerEnabled()) $tabs['sql'] = 'SQL';
            @endphp
            @foreach($tabs as $tab => $label)
                <x-filament::tabs.item :active="$activeTab === $tab" wire:click="switchTab('{{ $tab }}')">{{ $label }}</x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <div class="fdb-content">
            @if($activeTab === 'rows')
                @include('filament-database::pages.partials.rows')
            @elseif($activeTab === 'structure')
                @include('filament-database::pages.partials.structure')
            @elseif($activeTab === 'indexes')
                @include('filament-database::pages.partials.indexes')
            @elseif($activeTab === 'foreign-keys')
                @include('filament-database::pages.partials.foreign-keys')
            @elseif($activeTab === 'relationships')
                @include('filament-database::pages.partials.relationships')
            @elseif($activeTab === 'sql')
                @include('filament-database::pages.partials.sql')
            @endif
        </div>
    @else
        @if($activeConnection && $this->isConnectionHealthy($activeConnection))
            @php
                $overview = $this->getDatabaseOverview($activeConnection);
            @endphp
            <div class="fdb-overview">
                <div class="fdb-overview-header">
                    <h2>Database Overview</h2>
                    <div class="fdb-overview-connection">
                        <strong>{{ $overview['database'] }}</strong> &middot; {{ ucfirst($overview['driver']) }}
                    </div>
                </div>

                <div class="fdb-overview-stats">
                    <div class="fdb-stat-card">
                        <div class="fdb-stat-value">{{ number_format($overview['total_tables']) }}</div>
                        <div class="fdb-stat-label">Total Tables</div>
                    </div>
                    <div class="fdb-stat-card">
                        <div class="fdb-stat-value">{{ number_format($overview['total_rows']) }}</div>
                        <div class="fdb-stat-label">Total Rows</div>
                    </div>
                </div>

                @if(count($overview['largest_tables']) > 0)
                    <div class="fdb-overview-section">
                        <h3>Largest Tables</h3>
                        <div class="fdb-table-wrap">
                            <table class="fdb-table">
                                <thead>
                                    <tr>
                                        <th>Table Name</th>
                                        <th class="fdb-right">Rows</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($overview['largest_tables'] as $table)
                                        <tr wire:click="selectTable('{{ $table['name'] }}')" style="cursor: pointer;">
                                            <td class="fdb-bold">{{ $table['name'] }}</td>
                                            <td class="fdb-right">{{ number_format($table['rows']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="fdb-empty">
                <div>
                    <x-heroicon-o-circle-stack />
                    <p>Select a table to get started</p>
                </div>
            </div>
        @endif
    @endif

    @if($showCreateTable) @include('filament-database::pages.partials.create-table-modal') @endif
    @if($showAddColumn) @include('filament-database::pages.partials.add-column-modal') @endif
</x-filament-panels::page>
