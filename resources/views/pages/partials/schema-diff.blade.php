@if($showSchemaDiff && $schemaDiff)
    <div class="fdb-modal-overlay" wire:click.self="closeSchemaDiff">
        <div class="fdb-modal fdb-modal-lg" @click.stop>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">Schema Comparison</h3>
                <x-filament::icon-button
                    icon="heroicon-m-x-mark"
                    wire:click="closeSchemaDiff"
                />
            </div>

            @if($selectedSnapshot)
                <div style="margin-bottom: 1rem; padding: 0.75rem; background: var(--gray-50); border-radius: 0.5rem; font-size: 0.875rem;">
                    <strong>Comparing with:</strong> {{ date('Y-m-d H:i:s', strtotime($selectedSnapshot['timestamp'] ?? '')) }}
                    ({{ $selectedSnapshot['connection'] ?? '' }})
                </div>
            @endif

            <div style="max-height: 60vh; overflow-y: auto;">
                @php
                    $hasChanges = !empty($schemaDiff['tables_added']) || 
                                  !empty($schemaDiff['tables_removed']) || 
                                  !empty($schemaDiff['tables_modified']);
                @endphp

                @if(!$hasChanges)
                    <div class="fdb-empty" style="height: auto; padding: 2rem;">
                        <div>
                            <x-heroicon-o-check-circle style="color: var(--success-500);" />
                            <p>No schema changes detected</p>
                        </div>
                    </div>
                @else
                    {{-- Tables Added --}}
                    @if(!empty($schemaDiff['tables_added']))
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: #16a34a; margin-bottom: 0.5rem;">
                                ➕ Tables Added ({{ count($schemaDiff['tables_added']) }})
                            </h4>
                            <div class="fdb-table-wrap">
                                <table class="fdb-table">
                                    <tbody>
                                        @foreach($schemaDiff['tables_added'] as $table)
                                            <tr>
                                                <td class="fdb-bold" style="color: #16a34a;">{{ $table }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Tables Removed --}}
                    @if(!empty($schemaDiff['tables_removed']))
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: #dc2626; margin-bottom: 0.5rem;">
                                ➖ Tables Removed ({{ count($schemaDiff['tables_removed']) }})
                            </h4>
                            <div class="fdb-table-wrap">
                                <table class="fdb-table">
                                    <tbody>
                                        @foreach($schemaDiff['tables_removed'] as $table)
                                            <tr>
                                                <td class="fdb-bold" style="color: #dc2626;">{{ $table }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Tables Modified --}}
                    @if(!empty($schemaDiff['tables_modified']))
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 600; color: #ca8a04; margin-bottom: 0.5rem;">
                                🔄 Tables Modified ({{ count($schemaDiff['tables_modified']) }})
                            </h4>
                            @foreach($schemaDiff['tables_modified'] as $tableName => $changes)
                                <div style="margin-bottom: 1rem; padding: 0.75rem; border: 1px solid var(--gray-200); border-radius: 0.5rem;">
                                    <div style="font-weight: 600; margin-bottom: 0.5rem;">{{ $tableName }}</div>

                                    {{-- Columns Added --}}
                                    @if(!empty($changes['columns_added']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #16a34a; margin-bottom: 0.25rem;">
                                                Columns Added ({{ count($changes['columns_added']) }})
                                            </div>
                                            @foreach($changes['columns_added'] as $col)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #16a34a;">
                                                    + {{ $col['name'] }} ({{ $col['type'] }}){{ $col['nullable'] ? ' nullable' : '' }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Columns Removed --}}
                                    @if(!empty($changes['columns_removed']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #dc2626; margin-bottom: 0.25rem;">
                                                Columns Removed ({{ count($changes['columns_removed']) }})
                                            </div>
                                            @foreach($changes['columns_removed'] as $col)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #dc2626;">
                                                    - {{ $col['name'] }} ({{ $col['type'] }})
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Columns Modified --}}
                                    @if(!empty($changes['columns_modified']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #ca8a04; margin-bottom: 0.25rem;">
                                                Columns Modified ({{ count($changes['columns_modified']) }})
                                            </div>
                                            @foreach($changes['columns_modified'] as $mod)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #ca8a04;">
                                                    ~ {{ $mod['name'] }}: {{ $mod['old']['type'] }} → {{ $mod['new']['type'] }}
                                                    @if($mod['old']['nullable'] !== $mod['new']['nullable'])
                                                        (nullable: {{ $mod['old']['nullable'] ? 'yes' : 'no' }} → {{ $mod['new']['nullable'] ? 'yes' : 'no' }})
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Indexes Added --}}
                                    @if(!empty($changes['indexes_added']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #16a34a; margin-bottom: 0.25rem;">
                                                Indexes Added ({{ count($changes['indexes_added']) }})
                                            </div>
                                            @foreach($changes['indexes_added'] as $idx)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #16a34a;">
                                                    + {{ $idx['name'] }} ({{ implode(', ', $idx['columns'] ?? []) }})
                                                    {{ $idx['unique'] ? ' [UNIQUE]' : '' }}{{ $idx['primary'] ? ' [PRIMARY]' : '' }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Indexes Removed --}}
                                    @if(!empty($changes['indexes_removed']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #dc2626; margin-bottom: 0.25rem;">
                                                Indexes Removed ({{ count($changes['indexes_removed']) }})
                                            </div>
                                            @foreach($changes['indexes_removed'] as $idx)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #dc2626;">
                                                    - {{ $idx['name'] }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Foreign Keys Added --}}
                                    @if(!empty($changes['foreign_keys_added']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #16a34a; margin-bottom: 0.25rem;">
                                                Foreign Keys Added ({{ count($changes['foreign_keys_added']) }})
                                            </div>
                                            @foreach($changes['foreign_keys_added'] as $fk)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #16a34a;">
                                                    + {{ implode(', ', $fk['columns'] ?? []) }} → {{ $fk['foreign_table'] }}({{ implode(', ', $fk['foreign_columns'] ?? []) }})
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Foreign Keys Removed --}}
                                    @if(!empty($changes['foreign_keys_removed']))
                                        <div style="margin-bottom: 0.5rem;">
                                            <div style="font-size: 0.75rem; font-weight: 600; color: #dc2626; margin-bottom: 0.25rem;">
                                                Foreign Keys Removed ({{ count($changes['foreign_keys_removed']) }})
                                            </div>
                                            @foreach($changes['foreign_keys_removed'] as $fk)
                                                <div style="font-size: 0.875rem; padding-left: 1rem; color: #dc2626;">
                                                    - {{ $fk['name'] ?? 'unnamed' }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            @if($hasChanges)
                <div class="fdb-modal-footer" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                    @if(empty($generatedMigration))
                        <x-filament::button wire:click="generateMigrationCode" color="primary">
                            Generate Migration
                        </x-filament::button>
                    @else
                        <div style="width: 100%;">
                            <div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600;">Generated Migration:</span>
                                <div style="display: flex; gap: 0.5rem;">
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        x-data
                                        x-on:click="
                                            navigator.clipboard.writeText($wire.generatedMigration);
                                            $tooltip('Copied to clipboard!', { timeout: 1500 });
                                        "
                                    >
                                        Copy
                                    </x-filament::button>
                                    @if(!$this->isReadOnly())
                                        <x-filament::button
                                            size="sm"
                                            color="primary"
                                            wire:click="saveMigrationFile"
                                        >
                                            Save to database/migrations/
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                            <pre style="background: var(--gray-900); color: var(--gray-100); padding: 1rem; border-radius: 0.5rem; overflow-x: auto; font-size: 0.75rem; line-height: 1.5; max-height: 300px;"><code>{{ $generatedMigration }}</code></pre>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
