<div class="fdb-modal-overlay" wire:click.self="$set('showInsertRow', false)">
    <div class="fdb-modal fdb-modal-md">
        <h3>Insert Row into {{ $activeTable }}</h3>

        @foreach($insertRowData as $col => $val)
            <div class="fdb-field">
                <label>{{ $col }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="insertRowData.{{ $col }}" placeholder="NULL" />
                </x-filament::input.wrapper>
            </div>
        @endforeach

        <div class="fdb-modal-footer">
            <x-filament::button color="gray" wire:click="$set('showInsertRow', false)">Cancel</x-filament::button>
            <x-filament::button wire:click="saveInsertRow">Insert</x-filament::button>
        </div>
    </div>
</div>
