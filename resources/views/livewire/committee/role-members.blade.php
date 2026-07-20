<div class="flex-col space-y-8" wire:init="loadMembers">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl" class="flex gap-4">
                {{ __('roles.membership_headline', ['name' => $role->getFirstAttribute('description')]) }}
                @if($isModerator)
                    <flux:button
                        variant="subtle"
                        icon="pencil"
                        class="-mt-1"
                        :href="route('committees.roles.edit', ['realm' => $uid, 'cn' => $cn, 'ou' => $ou])"
                        title="{{ __('common.edit') }}"
                    />
                @endif
            </flux:heading>
            <flux:text class="text-base">{{ __('roles.membership_explanation') }}</flux:text>
        </div>
        <div class="flex flex-col gap-2">
            <flux:button
                variant="primary"
                icon="user-plus"
                :href="$isModerator ? route('committees.roles.add-member', ['realm' => $uid, 'cn' => $cn, 'ou' => $ou]) : null"
                :disabled="!$isModerator"
            >
                {{ __('common.add_member') }}
            </flux:button>
            <flux:button
                icon="calendar-x"
                :href="$isModerator ? route('committees.roles.terminate-memberships', ['realm' => $uid, 'cn' => $cn, 'ou' => $ou]) : null"
                :disabled="!$isModerator"
            >
                {{ __('roles.members.terminate_memberships') }}
            </flux:button>
        </div>
    </div>

    <div
        class="flex items-center gap-3"
        x-data="{ showOnlyActive: $persist(true).as('roleMembers.showOnlyActive') }"
        x-init="
            $wire.showOnlyActive = showOnlyActive;
            $watch('$wire.showOnlyActive', value => showOnlyActive = value);
        "
    >
        <flux:switch wire:model.live="showOnlyActive" label="{{ __('profile.show_only_active_memberships') }}" align="left" />
    </div>

    <flux:field>
        <flux:label>{{ __('roles.members.search') }}</flux:label>
        <flux:input
            icon="search"
            clearable
            wire:model.live="search"
        />
    </flux:field>

    <div class="pb-6 sm:pb-8">
        @if (! $ready)
            <div class="flex justify-center py-16">
                <flux:icon.loading />
            </div>
        @else
            <div wire:loading.flex wire:target="showOnlyActive" class="flex justify-center py-4">
                <flux:icon.loading />
            </div>
            <div wire:loading.remove wire:target="showOnlyActive">
                @if(count($members) > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-[55px]"></flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">{{ __('common.user') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'from'" :direction="$sortDirection" wire:click="sortBy('from')">{{ __('roles.membership_from') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'until'" :direction="$sortDirection" wire:click="sortBy('until')">{{ __('roles.membership_until') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'decided'" :direction="$sortDirection" wire:click="sortBy('decided')">{{ __('roles.membership_decided') }}</flux:table.column>
                        <flux:table.column>{{ __('roles.membership_comment') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
            @foreach($members as $member)
                <flux:table.row>
                    <flux:table.cell>
                        @php
                            $user = $userCache[$member->username] ?? null;
                            $jpegPhoto = $user?->getFirstAttribute('jpegPhoto');
                            if ($jpegPhoto) {
                                $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                            }
                            $displayName = $user?->getFirstAttribute('cn') ?? $member->username;
                            $status = $memberStatuses[$member->id];
                        @endphp
                        @if($status['isActive'] && !$status['isPending'])
                            <flux:avatar
                                badge badge:color="green"
                                src="{{ $jpegPhoto }}"
                                name="{{ $displayName }}"
                            />
                        @elseif($status['isPending'])
                            <flux:avatar
                                badge badge:color="yellow"
                                src="{{ $jpegPhoto }}"
                                name="{{ $displayName }}"
                            />
                        @else
                            <flux:avatar
                                badge badge:color="gray"
                                src="{{ $jpegPhoto }}"
                                name="{{ $displayName }}"
                            />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($isAdmin)
                            <flux:link
                                wire:navigate
                                :href="route('profile', ['realm' => $uid, 'username' => $member->username])"
                            >
                                {{ $displayName }}
                            </flux:link>
                        @else
                            {{ $displayName }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ \Carbon\Carbon::parse($member->from)->format('Y-m-d') }}</flux:table.cell>
                    <flux:table.cell>
                        @empty($member->until)
                            <flux:separator />
                        @else
                            {{ \Carbon\Carbon::parse($member->until)->format('Y-m-d') }}
                        @endempty
                    </flux:table.cell>
                    <flux:table.cell>
                        @empty($member->decided)
                            <flux:separator />
                        @else
                            {{ \Carbon\Carbon::parse($member->decided)->format('Y-m-d') }}
                        @endempty
                    </flux:table.cell>
                    <flux:table.cell>
                        @empty($member->comment)
                            <flux:separator />
                        @else
                            {{ $member->comment }}
                        @endempty
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end items-center gap-2">
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item
                                        icon="pencil"
                                        wire:navigate
                                        :disabled="!$isModerator"
                                        :href="$isModerator ? route('committees.roles.members.edit', ['realm' => $uid, 'ou' => $ou, 'cn' => $cn, 'id' => $member->id]) : null"
                                    >
                                        {{ __('roles.link_edit') }}
                                    </flux:menu.item>
                                    <flux:modal.trigger name="delete">
                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash-2"
                                            wire:click="prepareDeletion({{ $member->id }})"
                                            :disabled="!$isModerator"
                                        >
                                            {{ __('common.delete') }}
                                        </flux:menu.item>
                                    </flux:modal.trigger>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
            </flux:table.rows>
        </flux:table>

                    <div class="pagination">
                        <flux:pagination :paginator="$members" />
                    </div>
                @else
                    <flux:callout variant="warning" icon="circle-alert" heading="{{ __('roles.no_members_found') }}" />
                @endif
    </div>
    @endif

    <form wire:submit="commitDeletion">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('roles.members.delete_title', ['name' => $deleteDisplayName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('roles.members.delete_text', ['name' => $deleteDisplayName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
