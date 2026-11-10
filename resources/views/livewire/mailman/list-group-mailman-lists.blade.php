<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('group_mailman_lists.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('group_mailman_lists.explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.group-mailman-lists.new', ['realm' => $uid])"
            >
                {{ __('group_mailman_lists.new') }}
            </flux:button>
        </div>
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('group_mailman_lists.search') }}</flux:label>
        <flux:input icon="search" wire:model.live="search" />
    </flux:field>

    <div class="pb-8">
        @if($missingStatusIds !== '')
            {{-- Re-keyed per page/search so Alpine runs x-init again for newly shown rows. --}}
            <div class="hidden" wire:key="load-statuses-{{ $missingStatusIds }}" x-init="$wire.loadStatuses()"></div>
        @endif
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'mailman_list_id'" :direction="$sortDirection" wire:click="sortBy('mailman_list_id')">
                    {{ __('group_mailman_lists.mailman_list_id') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'group'" :direction="$sortDirection" wire:click="sortBy('group')">
                    {{ __('group_mailman_lists.role_member') }}
                </flux:table.column>
                <flux:table.column>{{ __('group_mailman_lists.role_moderator') }}</flux:table.column>
                <flux:table.column>{{ __('group_mailman_lists.status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
            @forelse($mappings as $mapping)
                <flux:table.row wire:key="mapping-{{ $mapping->id }}">
                    <flux:table.cell>
                        @if($mapping->web_url)
                            <flux:link href="{{ $mapping->web_url }}" target="_blank" rel="noopener noreferrer">{{ $mapping->mailman_list_id }}</flux:link>
                        @else
                            <span class="text-zinc-800 dark:text-white">{{ $mapping->mailman_list_id }}</span>
                        @endif
                    </flux:table.cell>
                    @foreach(['member', 'moderator'] as $role)
                        <flux:table.cell>
                            <div class="flex flex-wrap items-center gap-1.5">
                                @foreach($mapping->groups[$role] as $group)
                                    @if($group['exists'])
                                        <a wire:navigate href="{{ route('realms.groups.roles', ['realm' => $uid, 'cn' => $group['cn']]) }}" class="rounded-md outline-none focus-visible:ring-2 ring-(--color-accent-content)">
                                            <flux:badge size="sm" class="hover:bg-zinc-800/15 dark:hover:bg-white/20">{{ $group['cn'] }}</flux:badge>
                                        </a>
                                    @else
                                        <flux:badge size="sm" color="red" icon="circle-alert" :title="__('group_mailman_lists.group_missing')">
                                            {{ $group['cn'] }}
                                            <span class="sr-only">({{ __('group_mailman_lists.group_missing') }})</span>
                                        </flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell>
                        <div class="flex flex-wrap items-center gap-2">
                            @if(isset($statuses[$mapping->id]))
                                @include('livewire.mailman.mailman-list-status', $statuses[$mapping->id])
                            @else
                                <flux:icon.loading variant="mini" class="text-zinc-400" />
                            @endif
                            @unless($mapping->enabled)
                                <flux:badge size="sm" color="zinc">{{ __('group_mailman_lists.disabled') }}</flux:badge>
                            @endunless
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="flex justify-end gap-2">
                        <flux:dropdown>
                            <flux:button size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                <flux:menu.item
                                    icon="pencil"
                                    wire:navigate
                                    href="{{ route('realms.group-mailman-lists.edit', ['realm' => $uid, 'listId' => $mapping->mailman_list_id]) }}"
                                >
                                    {{ __('group_mailman_lists.edit') }}
                                </flux:menu.item>
                                <flux:menu.item
                                    icon="users"
                                    wire:navigate
                                    href="{{ route('realms.group-mailman-lists.members', ['realm' => $uid, 'listId' => $mapping->mailman_list_id]) }}"
                                >
                                    {{ __('group_mailman_lists.link_members') }}
                                </flux:menu.item>
                                @if($mapping->groups_complete && $mapping->enabled)
                                    <flux:menu.item
                                        icon="refresh-ccw"
                                        x-on:click="const sync = $wire.syncList({{ $mapping->id }}); window.withProgressToast?.({{ Js::from(__('group_mailman_lists.sync_running', ['name' => $mapping->mailman_list_id])) }}, sync)"
                                    >
                                        {{ __('group_mailman_lists.sync') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.item
                                    :icon="$mapping->enabled ? 'circle-pause' : 'circle-play'"
                                    wire:click="toggleEnabled({{ $mapping->id }})"
                                >
                                    {{ $mapping->enabled ? __('group_mailman_lists.disable') : __('group_mailman_lists.enable') }}
                                </flux:menu.item>
                                <flux:menu.item
                                    variant="danger"
                                    icon="trash-2"
                                    wire:click="deletePrepare({{ $mapping->id }})"
                                >
                                    {{ __('common.delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <div class="flex justify-center item-center">
                            <span class="text-gray-400 text-xl py-2 font-medium">{{ __('group_mailman_lists.no_mappings_found') }}</span>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
            </flux:table.rows>
        </flux:table>

        @if(count($mappings) > 0)
            <div class="pagination">
                <flux:pagination :paginator="$mappings" />
            </div>
        @endif
    </div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('group_mailman_lists.delete_title', ['name' => $deleteMappingLabel]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('group_mailman_lists.delete_warning', ['name' => $deleteMappingLabel]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDelete()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('group_mailman_lists.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
