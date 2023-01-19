<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('realms:index') }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="{{ __('realms.search') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('uid')" :direction="$sortField === 'uid' ? $sortDirection : null">
                {{ __('realms.shortcode') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('long_name')" :direction="$sortField === 'long_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($realms as $realm)
            <x-table.row>
                <x-table.cell>{{ $realm->uid }}</x-table.cell>
                <x-table.cell>{{ $realm->long_name }}</x-table.cell>
                <x-table.cell>
                    <x-link href="{{ route('realms.admins', $realm->uid) }}">{{ __('realms.manage_admins') }}</x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-link href="{{ route('realms.members', $realm->uid) }}">{{ __('realms.manage_members') }}</x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $realm->uid }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $realm->uid }}')">{{ __('Edit') }}</x-button.link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_realms_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $realms->links() }}

    <form wire:submit.prevent="saveEdit">
        <x-modal.dialog wire:model.defer="showEditModal">
            <x-slot:title>
                {{ __('realms.edit', ['name' => $editRealmOldName]) }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="editRealm.long_name">
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
                {{ __('realms.delete_title', ['name' => $deleteRealmName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_warning', ['name' => $deleteRealmName]) }}
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
                {{ __('realms.new') }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="newRealm.uid">
                    <x-slot:label>{{ __('realms.shortcode') }}</x-slot:label>
                </x-input.group>
                <x-input.group wire:model="newRealm.long_name">
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
