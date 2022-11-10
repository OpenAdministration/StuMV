<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('groups:index') }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="{{ __('groups.search') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('realm_uid')" :direction="$sortField === 'realm_uid' ? $sortDirection : null">
                Realm {{ __('realms.shortcode') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'name' ? $sortDirection : null"
                class="w-full"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($groups as $group)
            <x-table.row>
                <x-table.cell>{{ $group->realm_uid }}</x-table.cell>
                <x-table.cell>{{ $group->name }}</x-table.cell>
                <x-table.cell>
                    <x-button.link class="text-red-500 hover:text-red-700" wire:click="deletePrepare('{{ $group->id }}')">{{ __('Delete') }}</x-button.link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $group->id }}')">{{ __('Edit') }}</x-button.link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="3">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_groups_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $groups->links() }}

    <form wire:submit.prevent="saveEdit">
        <x-modal.dialog wire:model.defer="showEditModal">
            <x-slot:title>
                {{ __('groups.edit', ['name' => $editGroupOldName]) }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="editGroup.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>

    <form wire:submit.prevent="deleteCommit">
        <x-modal.confirmation wire:model.defer="showDeleteModal">
            <x-slot:title>
                {{ __('groups.delete_title', ['name' => $deleteGroupName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('groups.delete_warning', ['name' => $deleteGroupName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Löschen') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>

    <form wire:submit.prevent="saveNew">
        <x-modal.dialog wire:model.defer="showNewModal">
            <x-slot:title>
                {{ __('groups.new') }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="newGroup.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
                <x-select wire:model="newGroup.realm_uid" class="mt-2">
                    <x-slot:label>Realm</x-slot:label>
                    @forelse($realms as $realm)
                        <option value="{{ $realm->uid }}">{{ $realm->long_name }} ({{ $realm->uid }})</option>
                    @empty
                        <option value="invalid-realm">{{ __('realms.no_realms_exist') }}</option>
                    @endforelse
                </x-select>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>
</div>
