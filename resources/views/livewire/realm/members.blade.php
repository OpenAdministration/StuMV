<div class="max-w-7xl mx-auto w-full flex-col space-y-8 pb-6 sm:pb-8" wire:init="loadMembers">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.members_heading') }}</flux:heading>
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
                    @if($isAdmin)
                        <flux:table.column sortable :sorted="$sortField === 'uid'" :direction="$sortDirection" wire:click="sortBy('uid')">{{ __('realms.username') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'pwdLastSuccess'" :direction="$sortDirection" wire:click="sortBy('pwdLastSuccess')">{{ __('realms.last_login') }}</flux:table.column>
                    @endif
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
                                :href="route('profile', ['realm' => $community_name, 'username' => $realm_member->getFirstAttribute('uid')])"
                            >
                                {{ $realm_member->getFirstAttribute('cn') }}
                            </flux:link>
                        @else
                            {{ $realm_member->getFirstAttribute('cn') }}
                        @endif
                    </flux:table.cell>
                    @if($isAdmin)
                        <flux:table.cell>{{ $realm_member->getFirstAttribute('uid') }}</flux:table.cell>
                        <flux:table.cell>
                            @php($lastLogin = $realm_member->lastSuccessfulLogin())
                            @if($lastLogin)
                                <span wire:key="last-login-{{ $realm_member->getFirstAttribute('uid') }}" wire:ignore x-data="localDateTime('{{ $lastLogin->toIso8601String() }}')">{{ $lastLogin->format('Y-m-d H:i') }} UTC</span>
                            @else
                                {{ __('realms.never_logged_in') }}
                            @endif
                        </flux:table.cell>
                    @endif
                    <flux:table.cell>
                        <div class="flex justify-end items-center gap-2">
                            @if($isModerator)
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    icon="file-text"
                                    wire:navigate
                                    :href="route('profile.memberships.export', ['realm' => $community_name, 'username' => $realm_member->getFirstAttribute('uid')])"
                                >
                                    {{ __('profile.export_memberships_title') }}
                                </flux:button>
                            @endif
                            @if($isAdmin || $canRemoveMember)
                                <flux:dropdown>
                                    <flux:button size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @if($isAdmin)
                                            <flux:menu.item
                                                icon="pencil"
                                                wire:navigate
                                                :href="route('profile', ['realm' => $community_name, 'username' => $realm_member->getFirstAttribute('uid')])"
                                            >
                                                {{ __('common.edit') }}
                                            </flux:menu.item>
                                        @endif
                                        @if($canRemoveMember)
                                            <flux:menu.item
                                                variant="danger"
                                                icon="user-minus"
                                                wire:click="removePrepare('{{ $realm_member->getFirstAttribute('uid') }}')"
                                            >
                                                {{ __('realms.remove_member') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
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
