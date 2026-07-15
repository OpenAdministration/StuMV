<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1">
            <flux:heading size="xl" class="mb-4">{{ __('groups.roles_headline', ['name' => $group_cn]) }}</flux:heading>
            <flux:text class="text-base">{{  __('groups.roles_explanation', ['name' => $group_cn]) }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.groups.roles.add', ['realm' => $realm_uid, 'cn' => $group_cn])"
            >
                {{ __('Add Role') }}
            </flux:button>
        </div>
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('groups.roles.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div class="pb-8">
        @if(count($rows) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'committee'" :direction="$sortDirection" wire:click="sortBy('committee')">{{ __('groups.committee_name') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'role'" :direction="$sortDirection" wire:click="sortBy('role')">{{ __('groups.role_name') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($rows as $row)
                    @php($role = $row['role'])
                    @php($committee = $row['committee'])
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link
                                wire:navigate
                                href="{{ route('committees.roles', ['realm' => $realm_uid, 'ou' => $committee?->getFirstAttribute('ou')]) }}"
                            >
                                {{ $committee?->getFirstAttribute('description') }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link
                                wire:navigate
                                href="{{ route('committees.roles.members', ['realm' => $realm_uid, 'ou' => $committee?->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')]) }}"
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
                                        wire:click="deletePrepare({{ $row['groupRole']->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="pagination">
                <flux:pagination :paginator="$rows" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('groups.no_roles_found') }}" />
        @endif
    </div>

    <flux:modal name="delete">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="modal-header">{{ __('groups.delete_role_title', $deleteRoleName) }}</flux:heading>
                <flux:text class="mt-2">{{ __('groups.delete_role_warning', $deleteRoleName) }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" wire:click="deleteCommit">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
