<flux:dropdown>
    <flux:button icon="refresh-ccw" icon:trailing="chevron-down" />
    <flux:menu>
        <flux:menu.item
            icon="folder-tree"
            wire:click="sync"
            title="{{ __('sync.ldap_title') }}"
            :disabled="auth()->user()->cannot('superadmin')"
        >
            LDAP
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>