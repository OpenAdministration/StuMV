@can('superadmin', \App\Models\User::class)
    <flux:dropdown>
        <flux:button
            variant="ghost"
            icon="refresh-ccw"
        />
        <flux:menu>
            <flux:menu.item
                icon="folder-tree"
                wire:click="sync"
                title="{{ __('sync.ldap_title') }}"
            >
                LDAP
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
@endcan