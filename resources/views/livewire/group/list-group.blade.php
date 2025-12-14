<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('realms.groups_headline') }}</flux:heading>
            <flux:text class="text-base">{{  __('realms.groups_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.groups.new', ['uid' => $realm_uid])"
            >
                {{ __('New Group') }}
            </flux:button>
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('groups.search') }}</flux:label>
        <flux:input icon="search" wire:model.live.debounce="search" />
    </flux:field>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Short Name') }}</flux:table.column>
            <flux:table.column>{{ __('Full Name') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($groupSlice->items() as $group)
            <flux:table.row>
                <flux:table.cell>{{ $group->getFirstAttribute('cn') }}</x-table.cell>
                <flux:table.cell>{{ $group->getFirstAttribute('description') }}</x-table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="users"
                        href="{{ route('realms.groups.roles', ['uid' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                    >
                        {{ __('groups.manage_roles') }}
                    </flux:button>
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                icon="pencil"
                                href="{{ route('realms.groups.edit', ['uid' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                            >
                                {{ __('groups.link_edit') }}
                            </flux:menu.item>
                            <flux:menu.item
                                variant="danger"
                                icon="trash-2"
                                wire:click="deletePrepare('{{ $realm_uid }}', '{{ $group->getFirstAttribute('cn')}}')"
                            >
                                {{ __('Delete') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="5">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_groups_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    @if($groupSlice->hasPages())
        <div class="-mt-8">
            <flux:pagination :paginator="$groupSlice" />
        </div>
    @endif

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('groups.delete_title', ['name' => $deleteGroupName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('groups.delete_warning', ['name' => $deleteGroupName]) }}
            </x-slot:content>
            <x-slot:footer>
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Delete') }}</flux:button>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
