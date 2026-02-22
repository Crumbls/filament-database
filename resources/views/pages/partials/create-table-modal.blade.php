<div class="fdb-modal-overlay" wire:click.self="$set('showCreateTable', false)">
    <div class="fdb-modal fdb-modal-lg">
        <h3>Create Table</h3>

        <div class="fdb-field">
            <label>Table Name</label>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="newTableName" />
            </x-filament::input.wrapper>
        </div>

        <div class="fdb-field">
            <label>Columns</label>
            @foreach($newTableColumns as $i => $col)
                <div class="fdb-col-row">
                    <div class="fdb-col-row-name">
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="newTableColumns.{{ $i }}.name" placeholder="Name" />
                        </x-filament::input.wrapper>
                    </div>
                    <div class="fdb-col-row-type">
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model="newTableColumns.{{ $i }}.type">
                                @foreach($this->getColumnTypes() as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                    <label class="fdb-checkbox-label">
                        <input type="checkbox" wire:model="newTableColumns.{{ $i }}.nullable" /> Null
                    </label>
                    <x-filament::icon-button icon="heroicon-m-x-mark" color="danger" size="sm" wire:click="removeNewTableColumn({{ $i }})" />
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
