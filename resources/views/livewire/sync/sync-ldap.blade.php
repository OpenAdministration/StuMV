<div>
    @if(auth()->user()->can('moderator', [$community]) || auth()->user()->can('admin', [$community]) || auth()->user()->can('superadmin'))
        <flux:button
            variant="ghost"
            icon="folder-sync"
            wire:click="sync"
            title="__('sync.ldap_title')"
        />
    @endif
</div>