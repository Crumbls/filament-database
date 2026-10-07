<div>
    <div class="fdb-sql-heading">
        <h2>SQL workspace</h2>
        <span>Connection: {{ $activeConnection }} · {{ $this->isSqlReadOnly() ? 'Read-only queries' : 'Write queries enabled' }}</span>
    </div>
    <div class="fdb-field">
        <label class="fdb-sql-label" for="fdb-sql-query">SQL query</label>
        <textarea 
            id="fdb-sql-query"
            wire:model="sqlQuery" 
            rows="4" 
            placeholder="{{ $activeTable ? 'SELECT * FROM ' . $activeTable . ' LIMIT 100' : 'SELECT * FROM table_name LIMIT 100' }}"
            class="fdb-sql-textarea"
            @keydown.ctrl.enter="$wire.executeSql()"
            @keydown.meta.enter="$wire.executeSql()"
        ></textarea>
        <p style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
            Press <kbd style="padding: 0.125rem 0.375rem; background: var(--gray-200); border-radius: 0.25rem; font-family: monospace;">Ctrl+Enter</kbd> or <kbd style="padding: 0.125rem 0.375rem; background: var(--gray-200); border-radius: 0.25rem; font-family: monospace;">Cmd+Enter</kbd> to execute
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <x-filament::button wire:click="executeSql" icon="heroicon-m-play">Execute</x-filament::button>
        <x-filament::button wire:click="explainSql" icon="heroicon-m-light-bulb" color="info">Explain</x-filament::button>
        
        @php
            $driver = $this->getDriverName($activeConnection);
            $supportsAnalyze = in_array($driver, ['mysql', 'pgsql']);
        @endphp
        
        @if($supportsAnalyze)
            <div class="fdb-explain-mode" role="group" aria-label="Explain mode">
                <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; cursor: pointer;">
                    <input type="radio" wire:model.live="explainType" value="explain" style="cursor: pointer;">
                    <span>EXPLAIN</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; cursor: pointer;">
                    <input type="radio" wire:model.live="explainType" value="analyze" style="cursor: pointer;">
                    <span>EXPLAIN ANALYZE</span>
                </label>
            </div>
        @endif
    </div>

    @if(count($sqlHistory[$activeConnection] ?? []) > 0)
        <div style="margin-top: 1rem;">
            <h4 style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.5rem;">Recent Queries</h4>
            <div style="max-height: 12rem; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: 0.375rem; padding: 0.5rem;">
                @foreach($sqlHistory[$activeConnection] as $index => $item)
                    <button type="button" class="fdb-history-item" wire:click="loadHistoryQuery({{ $index }})" title="Load query: {{ $item['query'] }}">
                        <span style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                            <span style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ Str::limit($item['query'], 80) }}
                            </span>
                            <span style="display: flex; gap: 0.5rem; font-size: 0.7rem; color: var(--gray-500); white-space: nowrap;">
                                <span>{{ $item['time'] }}</span>
                                <span>{{ $item['rows'] }} row(s)</span>
                                <span>{{ $item['duration'] }}</span>
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if($sqlError)
        <div class="fdb-error-box" role="alert">{{ $sqlError }}</div>
    @elseif($sqlStatus)
        <p class="fdb-sql-status" role="status" aria-live="polite">{{ $sqlStatus }}</p>
    @endif

    @if(count($sqlResults))
        <div class="fdb-table-wrap" style="margin-top: 1rem;">
            <table class="fdb-table">
                <thead>
                    <tr>
                        @foreach(array_keys($sqlResults[0]) as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($sqlResults as $row)
                        <tr>
                            @foreach($row as $val)
                                <td style="white-space: nowrap; max-width: 20rem; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $val === null ? 'NULL' : Str::limit((string) $val, 80) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(count($explainResults))
        <div x-data="{ open: true }" style="margin-top: 1.5rem; border: 1px solid var(--primary-500); border-radius: 0.5rem; overflow: hidden;">
            <button type="button" class="fdb-plan-toggle" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" aria-controls="fdb-explain-results">
                <span style="font-weight: 600;">
                    Query Execution Plan
                    @if($explainType === 'analyze')
                        <span style="font-size: 0.75rem; font-weight: normal;">(ANALYZE)</span>
                    @endif
                </span>
                <svg x-show="!open" aria-hidden="true" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
                <svg x-show="open" aria-hidden="true" style="width: 1.25rem; height: 1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                </svg>
            </button>
            
            <div id="fdb-explain-results" class="fdb-plan-content" x-show="open" x-collapse>
                @if($explainFormat === 'table')
                    {{-- Table format for MySQL EXPLAIN and SQLite EXPLAIN QUERY PLAN --}}
                    <div class="fdb-table-wrap">
                        <table class="fdb-table">
                            <thead>
                                <tr>
                                    @foreach(array_keys($explainResults[0]) as $col)
                                        <th>{{ $col }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($explainResults as $row)
                                    <tr>
                                        @foreach($row as $val)
                                            <td style="white-space: nowrap; max-width: 20rem; overflow: hidden; text-overflow: ellipsis;">
                                                {{ $val === null ? 'NULL' : $val }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- Text format for PostgreSQL EXPLAIN and MySQL EXPLAIN ANALYZE --}}
                    <pre style="font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 0.875rem; line-height: 1.5; background: var(--gray-50); padding: 1rem; border-radius: 0.375rem; overflow-x: auto; margin: 0; white-space: pre-wrap; word-wrap: break-word;">{{ is_array($explainResults) ? implode("\n", $explainResults) : $explainResults }}</pre>
                @endif
            </div>
        </div>
    @endif
</div>
