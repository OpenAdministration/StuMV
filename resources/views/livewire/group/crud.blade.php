<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('groups:index') }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.live.debounce="search" placeholder="{{ __('groups.search') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('realm_uid')" :direction="$sortField === 'realm_uid' ? $sortDirection : null">
                Realm {{ __('realms.shortcode') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($groups as $group)
            <x-table.row>
                <x-table.cell>{{ $group->realm_uid }}</x-table.cell>
                <x-table.cell>{{ $group->name }}</x-table.cell>
                <x-table.cell>
                    <x-link href="{{ route('groups.roles', $group->id) }}">{{ __('groups.manage_roles') }}</x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $group->id }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $group->id }}')">{{ __('Edit') }}</x-button.link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="5">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_groups_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $groups->links() }}

    <form wire:submit="saveEdit">
        <x-modal.dialog wire:model="showEditModal">
            <x-slot:title>
                {{ __('groups.edit', ['name' => $editGroupOldName]) }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model.live="editGroup.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('groups.delete_title', ['name' => $deleteGroupName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('groups.delete_warning', ['name' => $deleteGroupName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>

    <form wire:submit="saveNew">
        <x-modal.dialog wire:model="showNewModal">
            <x-slot:title>
                {{ __('groups.new') }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model.live="newGroup.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
                <x-select wire:model.live="newGroup.realm_uid" class="mt-2">
                    <x-slot:label>Realm</x-slot:label>
                    <option value="please-select" selected="selected">{{ __('Please select') }}</option>
                    @foreach($realms as $realm)
                        <option value="{{ $realm->uid }}">{{ $realm->long_name }} ({{ $realm->uid }})</option>
                    @endforeach
                </x-select>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>
</div>
