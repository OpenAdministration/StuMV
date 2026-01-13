<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('committees.roles_heading', ['name' => $committee->getFirstAttribute('description')]) }}</flux:heading>
            <flux:text class="text-base">{{ __('committees.roles_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                :href="route('committees.roles.new', ['uid' => $uid, 'ou' => $ou])"
                :disabled="auth()->user()->cannot('create', [\App\Ldap\Role::class, $committee, $community])"
            >
                {{ __('New Role') }}
            </flux:button>
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('roles.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live.debounce="search" />
    </flux:field>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Full Name') }}</flux:table.column>
            <flux:table.column>{{ __('Members') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($roles as $role)
            <flux:table.row>
                <flux:table.cell>
                    <flux:link
                        wire:navigate
                        href="{{ route('committees.roles.members', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')]) }}"
                    >
                        {{ $role->getFirstAttribute('description') }}
                    </flux:link>
                </flux:table.cell>
                <flux:table.cell>
                    {{ $this->getMembersString($role) }}
                </flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="users"
                        :href="route('committees.roles.members', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')])"
                    >
                        {{ __('roles.link_members') }}
                    </flux:button>
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                icon="pencil"
                                :href="route('committees.roles.edit', ['uid' => $uid, 'ou' => $ou, 'cn' => $role->getFirstAttribute('cn')])"
                                :disabled="auth()->user()->cannot('edit', [$role, $committee, $community])"
                            >
                                {{ __('roles.link_edit') }}
                            </flux:menu.item>
                            <flux:menu.item
                                variant="danger"
                                icon="trash-2"
                                :disabled="auth()->user()->cannot('delete', [$role, $committee, $community])"
                                wire:click="deletePrepare('{{ $role->getFirstAttribute('cn') }}')">
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
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('roles.no_roles_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('roles.delete_title', ['name' => $deleteRoleCn]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('roles.delete_warning', ['name' => $deleteRoleName]) }}
            </x-slot:content>
            <x-slot:footer>
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Delete') }}</flux:button>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
