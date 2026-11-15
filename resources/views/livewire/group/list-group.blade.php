<div class="max-w-7xl mx-auto w-full flex-col">
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
                :href="route('realms.groups.new', ['realm' => $realm_uid])"
            >
                {{ __('groups.new_button') }}
            </flux:button>
        </div>
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('groups.search') }}</flux:label>
        <flux:input icon="search" wire:model.live="search" />
    </flux:field>

    <div class="pb-8">
        @if(count($groups) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'cn'" :direction="$sortDirection" wire:click="sortBy('cn')">
                        {{ __('common.short_name') }}
                    </flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'description'" :direction="$sortDirection" wire:click="sortBy('description')">
                        {{ __('groups.description_label') }}
                    </flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($groups as $group)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link
                                wire:navigate
                                href="{{ route('realms.groups.roles', ['realm' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                            >
                                {{ $group->getFirstAttribute('cn') }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $group->getFirstAttribute('description') }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                <flux:dropdown>
                                    <flux:button size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item
                                            icon="users"
                                            wire:navigate
                                            href="{{ route('realms.groups.members', ['realm' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                                        >
                                            {{ __('groups.link_members') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            icon="square-user"
                                            wire:navigate
                                            href="{{ route('realms.groups.roles', ['realm' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                                        >
                                            {{ __('groups.manage_roles') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            icon="pencil"
                                            href="{{ route('realms.groups.edit', ['realm' => $realm_uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                                        >
                                            {{ __('groups.link_edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash-2"
                                            wire:click="deletePrepare('{{ $realm_uid }}', '{{ $group->getFirstAttribute('cn')}}')"
                                        >
                                            {{ __('common.delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pagination">
                <flux:pagination :paginator="$groups" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('groups.no_groups_found') }}" />
        @endif
    </div>

    <flux:modal name="delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="modal-header">{{ __('groups.delete_title', ['name' => $deleteGroupName]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('groups.delete_warning', ['name' => $deleteGroupName]) }}</flux:text>
                <flux:text class="mt-2">{{ __('groups.delete.confirm') }}<strong>{{ $deleteGroupName }}</strong></flux:text>
                <flux:field class="mt-4">
                    <flux:input
                        wire:model="deleteConfirmText"
                        :placeholder="$deleteGroupName"
                    />
                    <flux:error name="deleteConfirmText" />
                </flux:field>
            </div>
            <div class="flex flex-wrap justify-end gap-4">
                <flux:button
                    icon="ban"
                    x-on:click="$flux.modal('delete').close()"
                >
                    {{ __('common.cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    icon="trash-2"
                    wire:click="deleteCommit()"
                >
                    {{ __('common.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
