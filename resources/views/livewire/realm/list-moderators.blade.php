<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.mods_heading', ['name' => $community->getFirstAttribute('description'), 'uid' => $community_name]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">{{ __('realms.mods_explanation') }}</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary :href="route('realms.mods.new', ['uid' => $community_name])" class="flex"
                :disabled="auth()->user()->cannot('add_moderator', $community)"
            >
                <x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('Add Moderators') }}
            </x-button.link-primary>
        </div>
    </div>
    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('realms.search_moderators') }}"/>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('full_name')" :direction="$sortField === 'full_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('username')" :direction="$sortField === 'from' ? $sortDirection : null"
            >
                {{ __('Username') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($realm_members as $realm_member)
            <x-table.row>
                <x-table.cell>{{ $realm_member->cn[0] }}</x-table.cell>
                <x-table.cell>{{ $realm_member->uid[0] }}</x-table.cell>
                <x-table.cell>
                    <x-button.link-danger icon-leading="fas-trash"
                        :disabled="auth()->user()->cannot('remove_moderator', $community)"
                        wire:click="deletePrepare('{{ $realm_member->uid[0] }}')">{{ __('Remove Moderator') }}
                    </x-button.link-danger>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_moderators_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('realms.delete_mod_title', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_mod_warning', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
