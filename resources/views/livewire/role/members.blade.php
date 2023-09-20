<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('roles:members', $role) }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.live.debounce="search" placeholder="{{ __('roles.search_members') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('full_name')" :direction="$sortField === 'full_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('from')" :direction="$sortField === 'from' ? $sortDirection : null"
            >
                {{ __('From') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('until')" :direction="$sortField === 'until' ? $sortDirection : null"
            >
                {{ __('Until') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($role_members as $role_members_rel)
            <x-table.row>
                <x-table.cell>{{ $role_members_rel->full_name }}</x-table.cell>
                <x-table.cell>{{ $role_members_rel->from }}</x-table.cell>
                <x-table.cell>{{ $role_members_rel->until }}</x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $role_members_rel->id }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $role_members_rel->id }}')">{{ __('Edit') }}</x-button.link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('roles.no_members_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $role_members->links() }}

    <form wire:submit="saveNew">
        <x-modal.dialog wire:model="showNewModal">
            <x-slot:title>
                {{ __('roles.new_member') }}
            </x-slot:title>
            <x-slot:content>
                <x-select wire:model.live="newRoleUserRel.user_id" class="mt-2">
                    <x-slot:label>{{ __('roles.new_member_label') }}</x-slot:label>
                    <option value="-1" selected="selected">{{ __('Please select') }}</option>
                    @foreach($realm_members as $realm_member)
                        <option value="{{ $realm_member->id }}">{{ $realm_member->full_name }} ({{ $realm_member->username }})</option>
                    @endforeach
                </x-select>
                <x-input.group wire:model.live="newRoleUserRel.from">
                    <x-slot:label>{{ __('From') }}</x-slot:label>
                </x-input.group>
                <x-input.group wire:model.live="newRoleUserRel.until">
                    <x-slot:label>{{ __('Until') }}</x-slot:label>
                </x-input.group>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>

    <form wire:submit="saveEdit">
        <x-modal.dialog wire:model="showEditModal">
            <x-slot:title>
                {{ __('roles.edit_member', ['name' => $editMemberOldName]) }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model.live="editRoleUserRel.from">
                    <x-slot:label>{{ __('From') }}</x-slot:label>
                </x-input.group>
                <x-input.group wire:model.live="editRoleUserRel.until">
                    <x-slot:label>{{ __('Until') }}</x-slot:label>
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
                {{ __('roles.delete_member_title', ['name' => $deleteMemberName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('roles.delete_member_warning', ['name' => $deleteMemberName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
