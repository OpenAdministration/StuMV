<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl" class="flex gap-4">
                {{ __('roles.membership_headline', ['name' => $role->getFirstAttribute('description')]) }}
                <flux:button
                    variant="subtle"
                    icon="pencil"
                    class="-mt-1"
                    :href="route('committees.roles.edit', ['uid' => $uid, 'cn' => $cn, 'ou' => $ou])"
                    title="{{ __('Edit') }}"
                />
            </flux:heading>
            <flux:text class="text-base">{{ __('roles.membership_explanation') }}</flux:text>
        </div>
        <div class="flex flex-col gap-2">
            <flux:button
                variant="primary"
                icon="user-plus"
                :href="route('committees.roles.add-member', ['uid' => $uid, 'cn' => $cn, 'ou' => $ou])"
                :disabled="auth()->user()->cannot('create', [\App\Models\RoleMembership::class, $committee, $community])"
            >
                {{ __('Add Member') }}
            </flux:button>
            <flux:button
                icon="calendar-x"
                :href="route('committees.roles.terminate-memberships', ['uid' => $uid, 'cn' => $cn, 'ou' => $ou])"
                :disabled="auth()->user()->cannot('create', [\App\Models\RoleMembership::class, $committee, $community])"
            >
                {{ __('roles.members.terminate_memberships') }}
            </flux:button>
        </div>
    </div>

    <div class="flex items-center">
        <flux:switch wire:model.live="showOnlyActive" label="{{ __('profile.showOnlyActiveMemberships') }}" align="left" />
    </div>

    {{--
    <flux:field>
        <flux:label>{{ __('roles.members.search') }}</flux:label>
        <flux:input
            icon="search"
            clearable
            wire:model.live.debounce="search"
        />
    </flux:field>
    --}}

    <div class="pb-6 sm:pb-8">
        <flux:table>
            <flux:table.columns>
                <flux:table.column></flux:table.column>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('From') }}</flux:table.column>
                <flux:table.column>{{ __('Until') }}</flux:table.column>
                <flux:table.column>{{ __('Decided') }}</flux:table.column>
                <flux:table.column>{{ __('Comment') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
            @forelse($members as $member)
                <flux:table.row>
                    <flux:table.cell>
                        @php
                            $jpegPhoto = \App\Ldap\User::findOrFailByUsername($member->username)->getFirstAttribute('jpegPhoto');
                            if ($jpegPhoto) {
                                $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                            }
                        @endphp
                        @if($member->isActive() && !$member->isPending())
                            <flux:avatar
                                badge badge:color="green"
                                src="{{ $jpegPhoto }}"
                                name="{{ \App\Ldap\User::findOrFailByUsername($member->username)->getFirstAttribute('cn') }}"
                            />
                        @elseif($member->isPending())
                            <flux:avatar
                                badge badge:color="yellow"
                                src="{{ $jpegPhoto }}"
                                name="{{ \App\Ldap\User::findOrFailByUsername($member->username)->getFirstAttribute('cn') }}"
                            />
                        @else
                            <flux:avatar
                                badge badge:color="gray"
                                src="{{ $jpegPhoto }}"
                                name="{{ \App\Ldap\User::findOrFailByUsername($member->username)->getFirstAttribute('cn') }}"
                            />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link
                            wire:navigate
                            :disabled="auth()->user()->cannot('admin', [$community])"
                            href="{{ route('profile', ['username' => $member->username]) }}"
                        >
                            {{ \App\Ldap\User::findOrFailByUsername($member->username)->getFirstAttribute('cn') }}
                        </flux:link>
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
                    <flux:table.cell class="flex justify-end gap-2">
                        <flux:dropdown>
                            <flux:button size="sm" icon="ellipsis-vertical" />
                            <flux:menu>
                                <flux:menu.item
                                    icon="pencil"
                                    wire:navigate
                                    :disabled="auth()->user()->cannot('edit', [$member, $committee, $community])"
                                    href="{{ route('committees.roles.members.edit', ['uid' => $uid, 'ou' => $ou, 'cn' => $cn, 'id' => $member->id]) }}"
                                >
                                    {{ __('roles.link_edit') }}
                                </flux:menu.item>
                                <flux:modal.trigger name="deletion">
                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash-2"
                                        wire:click="prepareDeletion({{ $member->id }})"
                                        :disabled="auth()->user()->cannot('delete', [$member, $committee, $community])"
                                    >
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:modal.trigger>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <div class="flex item-center py-2">
                            <flux:separator text="{{ __('roles.no_members_found') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <form wire:submit="commitDeletion">
        <flux:modal name="deletion">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('roles.members.delete_title', ['name' => $deleteUsername]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('roles.members.delete_text', ['name' => $deleteUsername]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
