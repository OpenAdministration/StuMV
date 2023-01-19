<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('committees:roles', $committee) }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="{{ __('roles.search') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading  class="text-left">
                {{ __('roles.members') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($roles as $role)
            <x-table.row>
                <x-table.cell>{{ $role->name }}</x-table.cell>
                <x-table.cell>
                    @forelse($role->members as $user_rel)
                        @if($loop->last)
                            {{ $user_rel->user->full_name }}
                        @else
                            {{ $user_rel->user->full_name }},
                        @endif
                    @empty
                        {{ __('roles.no_members') }}
                    @endforelse
                </x-table.cell>
                <x-table.cell>
                    <x-link href="{{ route('roles.members', $role->id) }}">{{ __('roles.manage_members') }}</x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $role->id }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $role->id }}')">{{ __('Edit') }}</x-button.link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('roles.no_roles_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $roles->links() }}

    <form wire:submit.prevent="saveEdit">
        <x-modal.dialog wire:model.defer="showEditModal">
            <x-slot:title>
                {{ __('roles.edit', ['name' => $editRoleOldName]) }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="editRole.name">
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
                {{ __('roles.delete_title', ['name' => $deleteRoleName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('roles.delete_warning', ['name' => $deleteRoleName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>

    <form wire:submit.prevent="saveNew">
        <x-modal.dialog wire:model.defer="showNewModal">
            <x-slot:title>
                {{ __('roles.new') }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="newRole.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>
</div>
