<div class="fdb-modal-overlay" wire:click.self="$set('showCreateTable', false)">
    <div class="fdb-modal fdb-modal-lg" role="dialog" aria-modal="true" aria-labelledby="fdb-create-table-title" tabindex="-1" x-data x-ref="dialog" x-trap.noscroll="true" x-init="$nextTick(() => $refs.dialog.focus())" x-on:keydown.escape.window="$wire.set('showCreateTable', false)">
        <h3 id="fdb-create-table-title">Create Table</h3>

        <div class="fdb-field">
            <label for="fdb-new-table-name">Table Name</label>
            <x-filament::input.wrapper>
                <x-filament::input id="fdb-new-table-name" type="text" wire:model="newTableName" />
            </x-filament::input.wrapper>
            @error('newTableName')
                <p class="text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="fdb-field">
            <p style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Columns</p>
            @foreach($newTableColumns as $i => $col)
                <div class="fdb-col-row">
                    <div class="fdb-col-row-name">
                        <label for="fdb-new-column-name-{{ $i }}">Column {{ $i + 1 }} name</label>
                        <x-filament::input.wrapper>
                            <x-filament::input id="fdb-new-column-name-{{ $i }}" type="text" wire:model="newTableColumns.{{ $i }}.name" placeholder="Name" />
                        </x-filament::input.wrapper>
                        @error("newTableColumns.{$i}.name")
                            <p class="text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="fdb-col-row-type">
                        <label for="fdb-new-column-type-{{ $i }}">Type</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="fdb-new-column-type-{{ $i }}" wire:model="newTableColumns.{{ $i }}.type">
                                @foreach($this->getColumnTypes() as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        @error("newTableColumns.{$i}.type")
                            <p class="text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <label class="fdb-checkbox-label">
                        <input type="checkbox" wire:model="newTableColumns.{{ $i }}.nullable" /> Null
                    </label>
                    <x-filament::icon-button icon="heroicon-m-x-mark" color="danger" size="sm" aria-label="Remove column {{ $i + 1 }}" wire:click="removeNewTableColumn({{ $i }})" />
                </div>
            @endforeach
            <x-filament::link wire:click="addNewTableColumn" icon="heroicon-m-plus" size="sm">Add Column</x-filament::link>
        </div>

        <div class="fdb-modal-footer">
            <x-filament::button color="gray" wire:click="$set('showCreateTable', false)">Cancel</x-filament::button>
            <x-filament::button wire:click="saveCreateTable">Create Table</x-filament::button>
        </div>
    </div>
</div>
