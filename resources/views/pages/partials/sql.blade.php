<div>
    <div class="fdb-field">
        <textarea wire:model="sqlQuery" rows="4" placeholder="SELECT * FROM {{ $activeTable }} LIMIT 100" class="fdb-sql-textarea"></textarea>
    </div>

    <x-filament::button wire:click="executeSql" icon="heroicon-m-play">Execute</x-filament::button>

    @if(count($sqlHistory) > 0)
        <div style="margin-top: 1rem;">
            <h4 style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--gray-500); margin-bottom: 0.5rem;">Recent Queries</h4>
            <div style="max-height: 12rem; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: 0.375rem; padding: 0.5rem; background: var(--gray-50);">
                @foreach($sqlHistory as $index => $item)
                    <div wire:click="loadHistoryQuery({{ $index }})"
                         style="cursor: pointer; padding: 0.375rem 0.5rem; margin-bottom: 0.25rem; font-size: 0.75rem; font-family: monospace; background: white; border-radius: 0.25rem; border: 1px solid var(--gray-200);"
                         onmouseover="this.style.background='var(--gray-100)'"
                         onmouseout="this.style.background='white'">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--gray-700);">
                                {{ Str::limit($item['query'], 80) }}
                            </div>
                            <div style="display: flex; gap: 0.5rem; font-size: 0.7rem; color: var(--gray-500); white-space: nowrap;">
                                <span>{{ $item['time'] }}</span>
                                <span>{{ $item['rows'] }} row(s)</span>
                                <span>{{ $item['duration'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($sqlError)
        <div class="fdb-error-box">{{ $sqlError }}</div>
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
        <p class="fdb-result-count">{{ count($sqlResults) }} row(s) returned</p>
    @endif
</div>
