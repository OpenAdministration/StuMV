<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('groups:roles', $group) }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="{{ __('groups.search_roles') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'full_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('committee_name')" :direction="$sortField === 'from' ? $sortDirection : null"
            >
                {{ __('groups.committee_name') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($group_roles as $group_role)
            <x-table.row>
                <x-table.cell>{{ $group_role->name }}</x-table.cell>
                <x-table.cell>{{ $group_role->committee_name }}</x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $group_role->id }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_roles_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $group_roles->links() }}

    <form wire:submit.prevent="saveNew">
        <x-modal.dialog wire:model.defer="showNewModal">
            <x-slot:title>
                {{ __('groups.new_role') }}
            </x-slot:title>
            <x-slot:content>
                <x-select wire:model="newRole.id" class="mt-2">
                    <x-slot:label>{{ __('groups.new_role_label') }}</x-slot:label>
                    <option value="-1" selected="selected">{{ __('Please select') }}</option>
                    @foreach($free_roles as $free_role)
                        <option value="{{ $free_role->id }}">{{ $free_role->name }} ({{ $free_role->committee_name }})</option>
                    @endforeach
                </x-select>
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
                {{ __('groups.delete_role_title', ['name' => $deleteRoleName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('groups.delete_role_warning', ['name' => $deleteRoleName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
