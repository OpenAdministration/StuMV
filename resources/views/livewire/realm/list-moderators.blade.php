<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.mods_heading', ['name' => $community->getFirstAttribute('description'), 'uid' => $community_name]) }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.mods_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="user-plus"
                wire:navigate
                :href="route('realms.mods.new', ['uid' => $community_name])"
                :disabled="auth()->user()->cannot('add_moderator', $community)"
            >
                {{ __('Add Moderators') }}
            </flux:button>
        </div>
    </div>

    <!--<flux:field>
        <flux:label>{{ __('realms.search_moderators') }}</flux:label>
        <flux:input type="text" icon="magnifying-glass" wire:model.live.debounce="search" />
    </flux:field>-->

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Username') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($realm_members as $realm_member)
            <flux:table.row>
                <flux:table.cell>
                    @can('admin', $community)
                        <flux:link
                            wire:navigate
                            :disabled="auth()->user()->cannot('admin', [$community])"
                            href="{{ route('profile', ['username' => $realm_member->uid[0]]) }}"
                        >
                            {{ $realm_member->cn[0] }}
                        </flux:link>
                    @else
                        {{ $realm_member->cn[0] }}
                    @endcan
                </flux:table.cell>
                <flux:table.cell>{{ $realm_member->uid[0] }}</flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                variant="danger"
                                icon="user-minus"
                                :disabled="auth()->user()->cannot('remove_moderator', $community)"
                                wire:click="deletePrepare('{{ $realm_member->uid[0] }}')"
                            >
                                {{ __('Remove Moderator') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_moderators_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('realms.delete_mod_title', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_mod_warning', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}
            </x-slot:content>
            <x-slot:footer>
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Delete') }}</flux:button>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
