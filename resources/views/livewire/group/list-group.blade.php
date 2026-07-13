<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1">
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

    <flux:field class="mb-8">
        <flux:label>{{ __('groups.search') }}</flux:label>
        <flux:input icon="search" wire:model.live.debounce.500ms="search" />
    </flux:field>

    <div class="pb-8">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'cn'" :direction="$sortDirection" wire:click="sortBy('cn')">
                    {{ __('Short Name') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'description'" :direction="$sortDirection" wire:click="sortBy('description')">
                    {{ __('Full Name') }}
                </flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
            @forelse($groups as $group)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:link
                            wire:navigate
                            href="{{ route('realms.groups.roles', ['uid' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                        >
                            {{ $group->getFirstAttribute('cn') }}
                        </flux:link>
                    </x-table.cell>
                    <flux:table.cell>{{ $group->getFirstAttribute('description') }}</x-table.cell>
                    <flux:table.cell class="flex justify-end gap-2">
                        <flux:dropdown>
                            <flux:button size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                <flux:menu.item
                                    icon="users"
                                    wire:navigate
                                    href="{{ route('realms.groups.roles', ['uid' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                                >
                                    {{ __('groups.manage_roles') }}
                                </flux:menu.item>
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

        @if(count($groups) > 0)
            <div class="pagination">
                <flux:pagination :paginator="$groups" />
            </div>
        @endif
    </div>

    <flux:modal name="delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="modal-header">{{ __('groups.delete_title', ['name' => $deleteGroupName]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('groups.delete_warning', ['name' => $deleteGroupName]) }}</flux:text>
            </div>
            <div class="flex flex-wrap justify-end gap-4">
                <flux:button
                    icon="ban"
                    x-on:click="$flux.modal('delete').close()"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    icon="trash-2"
                    wire:click="deleteCommit()"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
