@php $columns = $this->getColumns($activeTable, $activeConnection); @endphp

@if(!$this->isReadOnly())
    <div class="fdb-actions-bar">
        <x-filament::button size="sm" wire:click="openAddColumn" icon="heroicon-m-plus">Add Column</x-filament::button>
    </div>
@endif

<div class="fdb-table-wrap">
    <table class="fdb-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Nullable</th>
                <th>Default</th>
                @if(!$this->isReadOnly())
                    <th class="fdb-right">Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($columns as $col)
                <tr>
                    <td class="fdb-bold">{{ $col['name'] }}</td>
                    <td>{{ $col['type_name'] ?? $col['type'] }}</td>
                    <td>{{ ($col['nullable'] ?? false) ? 'Yes' : 'No' }}</td>
                    <td>{{ $col['default'] ?? '—' }}</td>
                    @if(!$this->isReadOnly())
                        <td class="fdb-right" style="white-space: nowrap;">
                            {{ ($this->getEditColumnAction($col['name'])) }}
                            @if(!$this->isDestructivePrevented())
                                <x-filament::link color="danger" size="sm" wire:click="confirmDropColumn('{{ $col['name'] }}')" wire:confirm="Drop column '{{ $col['name'] }}'?">Drop</x-filament::link>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
