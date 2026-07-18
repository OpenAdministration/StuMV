<div>
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.edit_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('oidc_clients.explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('oidc_clients.name') }}</flux:label>
            <flux:input wire:model="name" placeholder="{{ __('oidc_clients.name_placeholder') }}" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('oidc_clients.redirect_uris') }}</flux:label>
            <flux:description>{{ __('oidc_clients.redirect_uris_description') }}</flux:description>
            <flux:textarea wire:model="redirectUris" rows="4" placeholder="https://app.example.com/auth/callback" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('oidc_clients.scopes') }}</flux:label>
            <flux:checkbox.group wire:model="scopes">
                <flux:checkbox value="openid" label="{{ __('oidc_clients.scope_openid') }}" />
                <flux:checkbox value="profile" label="{{ __('oidc_clients.scope_profile') }}" />
                <flux:checkbox value="email" label="{{ __('oidc_clients.scope_email') }}" />
                <flux:checkbox value="phone" label="{{ __('oidc_clients.scope_phone') }}" />
                <flux:checkbox value="address" label="{{ __('oidc_clients.scope_address') }}" />
                <flux:checkbox value="committees" label="{{ __('oidc_clients.scope_committees') }}" />
                <flux:checkbox value="groups" label="{{ __('oidc_clients.scope_groups') }}" />
                <flux:checkbox value="users" label="{{ __('oidc_clients.scope_users') }}" />
            </flux:checkbox.group>
        </flux:field>

        <x-slot:abort_route>
            {{ route('oidc-clients.list') }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
