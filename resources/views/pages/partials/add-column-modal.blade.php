<div class="fdb-modal-overlay" wire:click.self="$set('showAddColumn', false)">
    <div class="fdb-modal fdb-modal-sm" role="dialog" aria-modal="true" aria-labelledby="fdb-add-column-title" tabindex="-1" x-data x-ref="dialog" x-trap.noscroll="true" x-init="$nextTick(() => $refs.dialog.focus())" x-on:keydown.escape.window="$wire.set('showAddColumn', false)">
        <h3 id="fdb-add-column-title">Add Column to {{ $activeTable }}</h3>

        <div class="fdb-field">
            <label for="fdb-column-name">Name</label>
            <x-filament::input.wrapper>
                <x-filament::input id="fdb-column-name" type="text" wire:model="newColumnName" />
            </x-filament::input.wrapper>
            @error('newColumnName')
                <p class="text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="fdb-field">
            <label for="fdb-column-type">Type</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="fdb-column-type" wire:model="newColumnType">
                    @foreach($this->getColumnTypes() as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            @error('newColumnType')
                <p class="text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </div>
        <div class="fdb-field">
            <label class="fdb-checkbox-label">
                <input type="checkbox" wire:model="newColumnNullable" /> Nullable
            </label>
        </div>
        <div class="fdb-field">
            <label for="fdb-column-default">Default (optional)</label>
            <x-filament::input.wrapper>
                <x-filament::input id="fdb-column-default" type="text" wire:model="newColumnDefault" />
            </x-filament::input.wrapper>
        </div>

        <div class="fdb-modal-footer">
            <x-filament::button color="gray" wire:click="$set('showAddColumn', false)">Cancel</x-filament::button>
            <x-filament::button wire:click="saveAddColumn">Add Column</x-filament::button>
        </div>
    </div>
</div>
