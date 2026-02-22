@php 
    $relationships = $this->getTableRelationships($activeTable, $activeConnection); 
    $references = $relationships['references'];
    $referencedBy = $relationships['referenced_by'];
@endphp

<div class="fdb-relationships">
    <div class="fdb-relationships-grid">
        {{-- Left Column: References (outgoing) --}}
        <div class="fdb-relationships-column">
            <h3 class="fdb-relationships-heading">References</h3>
            <p class="fdb-relationships-subheading">Tables this table points TO via foreign keys</p>
            
            @if(count($references) > 0)
                <div class="fdb-relationships-list">
                    @foreach($references as $fk)
                        @php
                            $foreignTable = $fk['foreign_table'] ?? '—';
                            $localCols = implode(', ', $fk['columns'] ?? []);
                            $foreignCols = implode(', ', $fk['foreign_columns'] ?? []);
                            $onUpdate = $fk['on_update'] ?? 'NO ACTION';
                            $onDelete = $fk['on_delete'] ?? 'NO ACTION';
                        @endphp
                        <div class="fdb-relationship-card">
                            <div class="fdb-relationship-arrow">→</div>
                            <div class="fdb-relationship-details">
                                <div class="fdb-relationship-mapping">
                                    <span class="fdb-relationship-column">{{ $localCols }}</span>
                                    <span class="fdb-relationship-separator">→</span>
                                    <button 
                                        type="button"
                                        wire:click="selectTable('{{ $foreignTable }}')" 
                                        class="fdb-relationship-table"
                                    >
                                        {{ $foreignTable }}.{{ $foreignCols }}
                                    </button>
                                </div>
                                <div class="fdb-relationship-actions">
                                    <span class="fdb-relationship-action">ON UPDATE: {{ $onUpdate }}</span>
                                    <span class="fdb-relationship-action">ON DELETE: {{ $onDelete }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="fdb-relationships-empty">
                    <x-heroicon-o-arrow-right-circle class="fdb-relationships-empty-icon" />
                    <p>This table does not reference any other tables.</p>
                </div>
            @endif
        </div>

        {{-- Right Column: Referenced By (incoming) --}}
        <div class="fdb-relationships-column">
            <h3 class="fdb-relationships-heading">Referenced By</h3>
            <p class="fdb-relationships-subheading">Tables that point TO this table</p>
            
            @if(count($referencedBy) > 0)
                <div class="fdb-relationships-list">
                    @foreach($referencedBy as $fk)
                        @php
                            $sourceTable = $fk['table'] ?? '—';
                            $localCols = implode(', ', $fk['columns'] ?? []);
                            $foreignCols = implode(', ', $fk['foreign_columns'] ?? []);
                            $onUpdate = $fk['on_update'] ?? 'NO ACTION';
                            $onDelete = $fk['on_delete'] ?? 'NO ACTION';
                        @endphp
                        <div class="fdb-relationship-card">
                            <div class="fdb-relationship-arrow fdb-relationship-arrow-incoming">←</div>
                            <div class="fdb-relationship-details">
                                <div class="fdb-relationship-mapping">
                                    <button 
                                        type="button"
                                        wire:click="selectTable('{{ $sourceTable }}')" 
                                        class="fdb-relationship-table"
                                    >
                                        {{ $sourceTable }}.{{ $localCols }}
                                    </button>
                                    <span class="fdb-relationship-separator">→</span>
                                    <span class="fdb-relationship-column">{{ $foreignCols }}</span>
                                </div>
                                <div class="fdb-relationship-actions">
                                    <span class="fdb-relationship-action">ON UPDATE: {{ $onUpdate }}</span>
                                    <span class="fdb-relationship-action">ON DELETE: {{ $onDelete }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="fdb-relationships-empty">
                    <x-heroicon-o-arrow-left-circle class="fdb-relationships-empty-icon" />
                    <p>No other tables reference this table.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .fdb-relationships { padding: 1rem 0; }
    .fdb-relationships-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
    @media (max-width: 768px) { .fdb-relationships-grid { grid-template-columns: 1fr; } }
    
    .fdb-relationships-column { min-width: 0; }
    .fdb-relationships-heading { 
        font-size: 1rem; 
        font-weight: 600; 
        color: var(--gray-900); 
        margin-bottom: 0.25rem; 
    }
    .dark .fdb-relationships-heading { color: var(--gray-100); }
    
    .fdb-relationships-subheading { 
        font-size: 0.75rem; 
        color: var(--gray-500); 
        margin-bottom: 1rem; 
    }
    
    .fdb-relationships-list { 
        display: flex; 
        flex-direction: column; 
        gap: 0.75rem; 
    }
    
    .fdb-relationship-card { 
        display: flex; 
        gap: 0.75rem; 
        padding: 1rem; 
        border-radius: 0.5rem; 
        border: 1px solid var(--gray-200); 
        background: var(--gray-50); 
        transition: all 0.2s; 
    }
    .dark .fdb-relationship-card { 
        background: var(--gray-800); 
        border-color: var(--gray-700); 
    }
    .fdb-relationship-card:hover { 
        border-color: var(--primary-400); 
        box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
    }
    
    .fdb-relationship-arrow { 
        font-size: 1.25rem; 
        font-weight: 700; 
        color: var(--primary-600); 
        flex-shrink: 0; 
    }
    .dark .fdb-relationship-arrow { color: var(--primary-400); }
    .fdb-relationship-arrow-incoming { color: var(--success-600); }
    .dark .fdb-relationship-arrow-incoming { color: var(--success-400); }
    
    .fdb-relationship-details { 
        flex: 1; 
        min-width: 0; 
        display: flex; 
        flex-direction: column; 
        gap: 0.5rem; 
    }
    
    .fdb-relationship-mapping { 
        display: flex; 
        align-items: center; 
        gap: 0.5rem; 
        font-size: 0.875rem; 
        flex-wrap: wrap; 
    }
    
    .fdb-relationship-column { 
        font-weight: 600; 
        color: var(--gray-900); 
        font-family: ui-monospace, monospace; 
    }
    .dark .fdb-relationship-column { color: var(--gray-100); }
    
    .fdb-relationship-separator { 
        color: var(--gray-400); 
        font-weight: 700; 
    }
    
    .fdb-relationship-table { 
        font-weight: 600; 
        color: var(--primary-600); 
        font-family: ui-monospace, monospace; 
        cursor: pointer; 
        background: none; 
        border: none; 
        padding: 0; 
        text-decoration: underline; 
        transition: color 0.2s; 
    }
    .dark .fdb-relationship-table { color: var(--primary-400); }
    .fdb-relationship-table:hover { 
        color: var(--primary-700); 
    }
    .dark .fdb-relationship-table:hover { color: var(--primary-300); }
    
    .fdb-relationship-actions { 
        display: flex; 
        gap: 1rem; 
        flex-wrap: wrap; 
    }
    
    .fdb-relationship-action { 
        font-size: 0.7rem; 
        font-weight: 600; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        color: var(--gray-500); 
    }
    
    .fdb-relationships-empty { 
        padding: 2rem; 
        text-align: center; 
        color: var(--gray-400); 
    }
    
    .fdb-relationships-empty-icon { 
        width: 3rem; 
        height: 3rem; 
        margin: 0 auto 0.75rem; 
        opacity: 0.5; 
    }
    
    .fdb-relationships-empty p { 
        font-size: 0.875rem; 
    }
</style>
