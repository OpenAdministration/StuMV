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

    <div class="pb-8">
        @if(count($mappings) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('group_mailman_lists.group') }}</flux:table.column>
                    <flux:table.column>{{ __('group_mailman_lists.mailman_list_id') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($mappings as $mapping)
                    <flux:table.row>
                        <flux:table.cell>
                            @if($mapping->group_cn)
                                <div class="font-medium">{{ $mapping->group_description ?: $mapping->group_cn }}</div>
                            @else
                                <flux:badge color="red" variant="solid">{{ __('group_mailman_lists.group_missing') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs text-zinc-500">{{ $mapping->mailman_list_id }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                <flux:button
                                    size="sm"
                                    icon="users"
                                    wire:navigate
                                    :href="route('realms.group-mailman-lists.members', ['realm' => $uid, 'listId' => $mapping->mailman_list_id])"
                                >
                                    {{ __('group_mailman_lists.link_members') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash-2"
                                    wire:click="deletePrepare('{{ $mapping->id }}')"
                                >
                                    {{ __('group_mailman_lists.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('group_mailman_lists.no_mappings_found') }}" />
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
