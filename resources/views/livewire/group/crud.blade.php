<div class="flex-col space-y-4">
    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('groups.search') }}"/>
        <x-button.link-primary :href="route('realms.groups.new', ['uid' => $realm_uid])" class="flex">
            <x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}
        </x-button.link-primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('cn')" :direction="$sortField === 'cn' ? $sortDirection : null">
                {{ __('Short Name') }}
            </x-table.heading>
            <x-table.heading sortable wire:click="sortBy('description')" :direction="$sortField === 'description' ? $sortDirection : null">
                {{ __('Long Name') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($groupSlice->items() as $group)
            <x-table.row>
                <x-table.cell>{{ $group->getFirstAttribute('cn') }}</x-table.cell>
                <x-table.cell>{{ $group->getFirstAttribute('description') }}</x-table.cell>
                <x-table.cell>
                    <x-link href="{{ route('realms.groups.roles', ['uid' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}">{{ __('groups.manage_roles') }}</x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger
                        wire:click="deletePrepare('{{ $realm_uid }}', '{{ $group->getFirstAttribute('cn')}}')"
                        wire:confirm="test"
                    >
                        {{ __('Delete') }}
                    </x-button.link-danger>
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
</div>
