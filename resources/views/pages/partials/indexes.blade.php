@php $indexes = $this->getIndexes($activeTable, $activeConnection); @endphp

<div class="fdb-table-wrap">
    <table class="fdb-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Columns</th>
                <th>Type</th>
                <th>Unique</th>
            </tr>
        </thead>
        <tbody>
            @forelse($indexes as $idx)
                <tr>
                    <td class="fdb-bold">{{ $idx['name'] }}</td>
                    <td>{{ implode(', ', $idx['columns'] ?? []) }}</td>
                    <td>{{ $idx['type'] ?? '—' }}</td>
                    <td>{{ ($idx['unique'] ?? false) ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="fdb-empty-cell">No indexes.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
