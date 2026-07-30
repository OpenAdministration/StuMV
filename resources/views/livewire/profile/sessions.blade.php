<div class="max-w-[calc(100%_+_4rem)]! w-[calc(100%_+_3rem)]! sm:w-[calc(100%_+_4rem)]! flex flex-col -m-6! sm:-m-8!">
    <div class="pt-6 sm:pt-8 px-6 sm:px-8 pb-3">
        <flux:heading size="xl" class="max-w-7xl mx-auto">{{ $givenName }} {{ $sn }}</flux:heading>
    </div>

    <x-navbar-profile :realm="$realm_uid" :username="$currentUsername" />

    <div class="flex-1 p-6 sm:p-8 overflow-y-auto">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row gap-6">
                <div class="flex-1 space-y-1">
                    <flux:text class="text-base">{{ __('profile.sessions_explanation') }}</flux:text>
                    @if($lastLogin)
                        <flux:text class="block text-sm text-zinc-500">{{ __('profile.sessions_last_login', ['datetime' => $lastLogin->format('Y-m-d H:i')]) }}</flux:text>
                    @endif
                </div>
                @if($sessions->contains(fn ($session) => $session->id !== $currentSessionId))
                    <div>
                        <flux:modal.trigger name="logout-other-sessions">
                            <flux:button variant="danger" icon="log-out">
                                {{ __('profile.log_out_other_sessions') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
            </div>

            @if(count($sessions) > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column sortable :sorted="$sortField === 'device'" :direction="$sortDirection" wire:click="sortBy('device')">{{ __('profile.sessions_device') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'ip_address'" :direction="$sortDirection" wire:click="sortBy('ip_address')">{{ __('profile.sessions_ip_address') }}</flux:table.column>
                        <flux:table.column sortable :sorted="$sortField === 'last_activity'" :direction="$sortDirection" wire:click="sortBy('last_activity')">{{ __('profile.sessions_last_active') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                    @foreach($sessions as $session)
                        <flux:table.row>
                            <flux:table.cell>
                                <div class="flex gap-4 items-center">
                                    <div class="max-w-md truncate" title="{{ $session->user_agent }}">{{ $session->device_description }}</div>
                                    @if($session->id === $currentSessionId)
                                        <flux:badge>{{ __('profile.sessions_current_device') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $session->ip_address ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end items-center gap-2">
                                    @if($session->id === $currentSessionId)
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            icon="log-out"
                                            :href="route('realm.logout.confirm', ['realm' => $realm_uid])"
                                            wire:navigate
                                        >
                                            {{ __('profile.log_out_session') }}
                                        </flux:button>
                                    @else
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            icon="log-out"
                                            wire:click="logoutSession('{{ $session->id }}')"
                                        >
                                            {{ __('profile.log_out_session') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:callout variant="warning" icon="circle-alert" heading="{{ __('profile.no_sessions_found') }}" />
            @endif
        </div>
    </div>

    <form wire:submit="logoutOtherSessions">
        <flux:modal name="logout-other-sessions">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('profile.log_out_other_sessions_confirm_title') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('profile.log_out_other_sessions_confirm_warning') }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeLogoutOtherSessionsModal">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('profile.log_out_other_sessions') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
