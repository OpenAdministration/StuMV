<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('committees.roles_heading', ['name' => $committee->getFirstAttribute('description')]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">{{ __('committees.roles_explanation') }}</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary
                :href="route('committees.roles.new', ['uid' => $uid, 'ou' => $ou])"
                icon-leading="fas-plus" :disabled="auth()->user()->cannot('create', [\App\Ldap\Role::class, $committee, $community])">
                {{ __('New Role') }}
            </x-button.link-primary>
        </div>
    </div>

    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('roles.search') }}"></x-input.group>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('cn')" :direction="$sortField === 'cn' ? $sortDirection : null">
                {{ __('Short Name') }}
            </x-table.heading>
            <x-table.heading sortable wire:click="sortBy('description')" :direction="$sortField === 'description' ? $sortDirection : null">
                {{ __('Full Name') }}
            </x-table.heading>
            <x-table.heading  class="text-left">
                {{ __('Members') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($rolesSlice->items() as $role)
            <x-table.row>
                <x-table.cell>{{ $role->getFirstAttribute('cn') }}</x-table.cell>
                <x-table.cell>{{ $role->getFirstAttribute('description') }}</x-table.cell>
                <x-table.cell>
                    @empty($this->getMembersString($role))
                        <span class="text-gray-400">{{ __('No members found') }}</span>
                    @else
                        {{ $this->getMembersString($role) }}
                    @endempty
                </x-table.cell>
                <x-table.cell class="flex space-x-5">
                    <x-link href="{{ route('committees.roles.members', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')]) }}">
                        <x-fas-users/>{{ __('roles.link_members') }}
                    </x-link>
                    <x-link :disabled="auth()->user()->cannot('edit', [$role, $committee, $community])">
                        <x-fas-pencil/>{{ __('roles.link_edit') }}
                    </x-link>
                    <x-button.link-danger icon-leading="fas-trash"
                        :disabled="auth()->user()->cannot('delete', [$role, $committee, $community])"
                        wire:click="deletePrepare('{{ $role->getFirstAttribute('cn') }}')">
                        {{ __('Delete') }}
                    </x-button.link-danger>
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

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('roles.delete_title', ['name' => $deleteRoleCn]) }}
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
</div>
