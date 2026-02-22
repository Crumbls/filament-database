<div class="fdb-modal-overlay" wire:click.self="$set('showEditRow', false)">
    <div class="fdb-modal fdb-modal-md">
        <h3>Edit Row</h3>

        @foreach($editRowData as $col => $val)
            <div class="fdb-field">
                <label>{{ $col }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="editRowData.{{ $col }}" />
                </x-filament::input.wrapper>
            </div>
        @endforeach

        <div class="fdb-modal-footer">
            <x-filament::button color="gray" wire:click="$set('showEditRow', false)">Cancel</x-filament::button>
            <x-filament::button wire:click="saveEditRow">Save</x-filament::button>
        </div>
    </div>
</div>
