<div class="flex-col space-y-8 pb-6 sm:pb-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.members_heading', ['name' => $community->getFirstAttribute('description'), 'uid' => $community_name]) }}</flux:text>
            <flux:text class="text-base">{{ __('realms.members_explanation') }}</flux:text>
        </div>
        <div>
            @can('add_member', $community)
                <flux:button
                    variant="primary"
                    icon="user-plus"
                    wire:navigate
                    href="{{ route('realms.members.new', ['uid' => $community_name]) }}"
                >
                    {{ __('Add Member') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('realms.search_members') }}</flux:label>
        <flux:input icon="search" wire:model.live.debounce.250ms="search" />
    </flux:field>

    <flux:table>
        <flux:table.columns>
            <flux:table.column class="w-[55px]"></flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($realm_members as $realm_member)
            <flux:table.row>
                <flux:table.cell>
                    @php
                        $jpegPhoto = \App\Ldap\User::findOrFailByUsername($realm_member->username)->getFirstAttribute('jpegPhoto') ?? null;
                        if ($jpegPhoto) {
                            $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                        }
                    @endphp
                    <flux:avatar
                        src="{{ $jpegPhoto }}"
                        name="{{ $realm_member->full_name }}"
                    />
                </flux:table.cell>
                <flux:table.cell>
                    @can('admin', $community)
                        <flux:link
                            wire:navigate
                            :disabled="auth()->user()->cannot('admin', [$community])"
                            href="{{ route('profile', ['username' => $realm_member->username]) }}"
                        >
                            {{ $realm_member->full_name }}
                        </flux:link>
                    @else
                        {{ $realm_member->full_name }}
                    @endcan
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex justify-end items-center gap-2">
                        @can('moderator', $community)
                            <flux:button
                                size="sm"
                                variant="primary"
                                icon="file-text"
                                wire:click="exportPdf('{{ $realm_member->username }}')"
                            >
                                {{ __('profile.membershipsAsPdf') }}
                            </flux:button>
                        @endcan
                        <flux:dropdown>
                            <flux:button size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                <flux:menu.item
                                    icon="pencil"
                                    :disabled="auth()->user()->cannot('admin', $community)"
                                    wire:navigate
                                    href="{{ route('profile', ['username' => $realm_member->username]) }}"
                                >
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.item
                                    variant="danger"
                                    icon="user-minus"
                                    :disabled="auth()->user()->cannot('remove_member', $community)"
                                    wire:click="deletePrepare('{{ $realm_member->username }}')"
                                >
                                    {{ __('Remove Member') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
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
        <div class="pagination -mt-8">
            <flux:pagination :paginator="$realm_members" />
        </div>
    @endif

    <div class="block h-[1px]"></div>

    <form wire:submit="deleteCommit">
        <flux:modal name="remove">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.delete_member_title', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.delete_member_warning', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
