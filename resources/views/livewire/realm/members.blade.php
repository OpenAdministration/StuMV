<div class="flex-col space-y-8 pb-6 sm:pb-8" wire:init="loadMembers">
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
                    href="{{ route('realms.members.new', ['realm' => $community_name]) }}"
                >
                    {{ __('common.add_member') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('realms.search_members') }}</flux:label>
        <flux:input icon="search" wire:model.live="search" />
    </flux:field>

    <div wire:loading.flex wire:target="loadMembers" class="flex justify-center py-16">
        <flux:icon.loading />
    </div>

    <div wire:loading.remove wire:target="loadMembers" class="pb-8">
        @if(count($realm_members) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-[55px]"></flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'cn'" :direction="$sortDirection" wire:click="sortBy('cn')">{{ __('Name') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
            @foreach($realm_members as $realm_member)
                <flux:table.row>
                    <flux:table.cell>
                        @php
                            $jpegPhoto = $realm_member->getFirstAttribute('jpegPhoto');
                            if ($jpegPhoto) {
                                $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                            }
                        @endphp
                        <flux:avatar
                            src="{{ $jpegPhoto }}"
                            name="{{ $realm_member->getFirstAttribute('cn') }}"
                        />
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($isAdmin)
                            <flux:link
                                wire:navigate
                                :href="route('profile', ['username' => $realm_member->getFirstAttribute('uid')])"
                            >
                                {{ $realm_member->getFirstAttribute('cn') }}
                            </flux:link>
                        @else
                            {{ $realm_member->getFirstAttribute('cn') }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end items-center gap-2">
                            @if($isModerator)
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="file-text"
                                    wire:click="exportPdf('{{ $realm_member->getFirstAttribute('uid') }}')"
                                >
                                    {{ __('profile.memberships_as_pdf') }}
                                </flux:button>
                            @endif
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item
                                        icon="pencil"
                                        :disabled="!$isAdmin"
                                        wire:navigate
                                        :href="$isAdmin ? route('profile', ['username' => $realm_member->getFirstAttribute('uid')]) : null"
                                    >
                                        {{ __('common.edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="user-minus"
                                        :disabled="!$canRemoveMember"
                                        wire:click="removePrepare('{{ $realm_member->getFirstAttribute('uid') }}')"
                                    >
                                        {{ __('realms.remove_member') }}
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
                <flux:pagination :paginator="$realm_members" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('realms.no_members_found') }}" />
        @endif

    <div class="block h-[1px]"></div>

    <form wire:submit="removeCommit">
        <flux:modal name="remove">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.delete_member_title', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.delete_member_warning', ['name' => $deleteMemberName, 'username' => $deleteMemberUsername]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
