<div class="flex-col space-y-8">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <flux:heading size="xl" class="mb-4">{{ __('groups.roles_headline', ['name' => $group_cn]) }}</flux:heading>
            <flux:text class="text-base">{{  __('groups.roles_explanation', ['name' => $group_cn]) }}</flux:text>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <flux:button
                variant="primary"
                :href="route('realms.groups.roles.add', ['uid' => $realm_uid, 'cn' => $group_cn])"
            >
                {{ __('Add Role') }}
            </flux:button>
        </div>
    </div>

    {{--
    <flux:field>
        <flux:label>{{ __('groups.roles.search') }}</flux:label>
        <flux:input wire:model.live.debounce="search" />
    </flux:field>
    --}}

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('groups.committee_name') }}</flux:table.column>
            <flux:table.column>{{ __('groups.role_name') }}</flux:table.column>
            <flux:table.column></flux:table.colum>
        </flux:table-columns>
        <flux:table.rows>
        @forelse($roles as $role)
            <flux:table.row>
                <flux:table.cell>
                    <flux:link
                        wire:navigate
                        href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $role->committee()->getFirstAttribute('ou')]) }}"
                    >
                        {{ $role->committee()->getFirstAttribute('description') }}
                    </flux:link>
                </flux:table.cell>
                <flux:table.cell>
                    <flux:link
                        wire:navigate
                        href="{{ route('committees.roles.members', ['uid' => $realm_uid, 'ou' => $role->committee()->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')]) }}"
                    >
                        {{ $role->getFirstAttribute('description') }}
                    </flux:link>
                </flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                variant="danger"
                                icon="trash"
                                wire:click="deletePrepare('{{ $role->getDn() }}')"
                            >
                                {{ __('Delete') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_roles_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="deleteCommit()">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('groups.delete_role_title', $deleteRoleName) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('groups.delete_role_warning', $deleteRoleName) }}
            </x-slot:content>
            <x-slot:footer>
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Delete') }}</flux:button>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
