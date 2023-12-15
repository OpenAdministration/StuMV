<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('groups.roles_headline') }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('groups.roles_explanation') }}
            </p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary :href="route('realms.groups.roles.add', ['uid' => $realm_uid, 'cn' => $group_cn])" class="flex">
                <x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('Add Role') }}
            </x-button.link-primary>
        </div>
    </div>
    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('groups.roles.search') }}"/>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('committee_name')" :direction="$sortField === 'from' ? $sortDirection : null"
            >
                {{ __('groups.committee_name') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'full_name' ? $sortDirection : null"
            >
                {{ __('groups.role_name') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($roles as $role)
            <x-table.row>
                <x-table.cell>
                    <x-link :href="route('committees.roles', ['uid' => $realm_uid, 'ou' => $role->committee()->getFirstAttribute('ou')])">
                        {{ $role->committee()->getFirstAttribute('description') }} ({{ $role->committee()->getFirstAttribute('ou') }})
                    </x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-link :href="route('committees.roles.members', ['uid' => $realm_uid, 'ou' => $role->committee()->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])">
                        {{ $role->getFirstAttribute('description') }} ({{ $role->getFirstAttribute('cn') }})
                    </x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $role->getDn() }}')">{{ __('Delete') }}</x-button.link-danger>
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

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('groups.delete_role_title', ['name' => $deleteRoleDN]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('groups.delete_role_warning', ['name' => $deleteRoleDN]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
