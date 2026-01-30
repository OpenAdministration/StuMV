<div>
    @can('superadmin', \App\Models\User::class)
        <flux:button
            variant="ghost"
            icon="folder-sync"
            wire:click="sync"
            title="__('sync.ldap_title')"
        />
    @endcan
</div>