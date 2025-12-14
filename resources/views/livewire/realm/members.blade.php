<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="space-y-4">
            <flux:heading size="xl">{{ __('realms.members_heading', ['name' => $community->getFirstAttribute('description'), 'uid' => $community_name]) }}</flux:text>
            <flux:text class="text-base">{{ __('realms.members_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="user-plus"
                wire:navigate
                href="{{ route('realms.members.new', ['uid' => $community_name]) }}"
                :disabled="auth()->user()->cannot('add_member', $community)">
                {{ __('Add Member') }}
            </flux:button>
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('realms.search_members') }}</flux:label>
        <flux:input icon="search" wire:model.live.debounce="search" />
    </flux:field>

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
                    <flux:link
                        wire:navigate
                        :disabled="auth()->user()->cannot('admin', [$community])"
                        href="{{ route('profile', ['username' => $realm_member->uid[0]]) }}"
                    >
                        {{ $realm_member->cn[0] }}
                    </flux:link>
                </flux:table.cell>
                <flux:table.cell>{{ $realm_member->uid[0] }}</flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="file-text"
                        wire:click="exportPdf('{{ $realm_member->uid[0] }}')"
                    >
                        {{ __('profile.membershipsAsPdf') }}
                    </flux:button>
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                icon="pencil"
                                :disabled="auth()->user()->cannot('admin', $community)"
                                wire:navigate
                                href="{{ route('profile', ['username' => $realm_member->uid[0]]) }}"
                            >
                                {{ __('Edit') }}
                            </flux:menu.item>
                            <flux:menu.item
                                variant="danger"
                                icon="user-minus"
                                :disabled="auth()->user()->cannot('remove_member', $community)"
                                wire:click="deletePrepare('{{ $realm_member->uid[0] }}')"
                            >
                                {{ __('Remove Member') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_members_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    @if(count($realm_members) > 0)
        <flux:pagination :paginator="$realm_members" />
    @endif

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('realms.delete_member_title', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_member_warning', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}
            </x-slot:content>
            <x-slot:footer>
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Delete') }}</flux:button>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
