<div>
    <flux:button
        variant="ghost"
        icon="folder-sync"
        wire:click="openSyncPreview"
        title="{{ __('sync.ldap_title') }}"
    />

    <flux:modal name="sync-ldap-preview" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="modal-header">{{ __('sync.ldap_preview_heading') }}</flux:heading>
                @if(!empty($preview['roles']) || !empty($preview['groups']))
                    <flux:text class="mt-2">{{ __('sync.ldap_preview_text') }}</flux:text>
                @endif
            </div>

            @if(empty($preview['roles']) && empty($preview['groups']))
                <flux:callout variant="success" icon="circle-check" heading="{{ __('sync.ldap_preview_empty') }}" />
            @else
                <div class="space-y-5 max-h-96 overflow-y-auto p-1 -m-1">
                    @if(!empty($preview['roles']))
                        <div class="space-y-3">
                            <flux:text class="font-medium uppercase tracking-wide text-xs text-zinc-500">{{ __('sync.roles_section') }}</flux:text>
                            @foreach($preview['roles'] as $entry)
                                <flux:fieldset>
                                    <flux:legend class="w-full flex flex-wrap items-center gap-1 py-3 border-b border-zinc-800/10 dark:border-white/20 font-bold">
                                        <flux:link href="{{ route('committees.roles', ['realm' => $uid, 'ou' => $entry['committee_ou']]) }}" target="_blank" rel="noopener noreferrer">{{ $entry['committee'] }}</flux:link>
                                        <span>&rsaquo;</span>
                                        <flux:link href="{{ route('committees.roles.members', ['realm' => $uid, 'ou' => $entry['committee_ou'], 'cn' => $entry['role_cn']]) }}" target="_blank" rel="noopener noreferrer">{{ $entry['role'] }}</flux:link>
                                    </flux:legend>
                                    <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700 px-4">
                                        @include('livewire.sync-ldap-member-list')
                                    </div>
                                </flux:fieldset>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($preview['groups']))
                        <div class="space-y-3">
                            <flux:text class="font-medium uppercase tracking-wide text-xs text-zinc-500">{{ __('sync.groups_section') }}</flux:text>
                            @foreach($preview['groups'] as $entry)
                                <flux:fieldset>
                                    <flux:legend class="w-full flex py-3 border-b border-zinc-800/10 dark:border-white/20 font-bold">
                                        <flux:link href="{{ route('realms.groups.members', ['realm' => $uid, 'cn' => $entry['group']]) }}" target="_blank" rel="noopener noreferrer">{{ $entry['group'] }}</flux:link>
                                    </flux:legend>
                                    <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700 px-4">
                                        @include('livewire.sync-ldap-member-list')
                                    </div>
                                </flux:fieldset>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-end gap-4">
                <flux:button icon="ban" x-on:click="$flux.modal('sync-ldap-preview').close()">{{ __('common.cancel') }}</flux:button>
                <flux:button variant="primary" icon="folder-sync" wire:click="syncLdap">{{ __('sync.confirm') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
