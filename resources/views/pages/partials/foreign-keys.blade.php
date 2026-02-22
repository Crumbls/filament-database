@php $fks = $this->getForeignKeys($activeTable, $activeConnection); @endphp

<div class="fdb-table-wrap">
    <table class="fdb-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Columns</th>
                <th>Foreign Table</th>
                <th>Foreign Columns</th>
                <th>On Update</th>
                <th>On Delete</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fks as $fk)
                <tr>
                    <td class="fdb-bold">{{ $fk['name'] }}</td>
                    <td>{{ implode(', ', $fk['columns'] ?? []) }}</td>
                    <td>{{ $fk['foreign_table'] ?? '—' }}</td>
                    <td>{{ implode(', ', $fk['foreign_columns'] ?? []) }}</td>
                    <td>{{ $fk['on_update'] ?? '—' }}</td>
                    <td>{{ $fk['on_delete'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="fdb-empty-cell">No foreign keys.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
