<div class="fdb-modal-overlay" wire:click.self="$set('showAddColumn', false)">
    <div class="fdb-modal fdb-modal-sm">
        <h3>Add Column to {{ $activeTable }}</h3>

        <div class="fdb-field">
            <label>Name</label>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="newColumnName" />
            </x-filament::input.wrapper>
        </div>
        <div class="fdb-field">
            <label>Type</label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model="newColumnType">
                    @foreach($this->getColumnTypes() as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="fdb-field">
            <label class="fdb-checkbox-label">
                <input type="checkbox" wire:model="newColumnNullable" /> Nullable
            </label>
        </div>
        <div class="fdb-field">
            <label>Default (optional)</label>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="newColumnDefault" />
            </x-filament::input.wrapper>
        </div>

        <div class="fdb-modal-footer">
            <x-filament::button color="gray" wire:click="$set('showAddColumn', false)">Cancel</x-filament::button>
            <x-filament::button wire:click="saveAddColumn">Add Column</x-filament::button>
        </div>
    </div>
</div>
