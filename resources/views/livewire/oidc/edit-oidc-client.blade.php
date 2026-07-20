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
            <flux:pillbox multiple wire:model="scopes">
                <flux:pillbox.option value="openid">{{ __('oidc_clients.scope_openid') }}</flux:pillbox.option>
                <flux:pillbox.option value="profile">{{ __('oidc_clients.scope_profile') }}</flux:pillbox.option>
                <flux:pillbox.option value="email">{{ __('oidc_clients.scope_email') }}</flux:pillbox.option>
                <flux:pillbox.option value="phone">{{ __('oidc_clients.scope_phone') }}</flux:pillbox.option>
                <flux:pillbox.option value="address">{{ __('oidc_clients.scope_address') }}</flux:pillbox.option>
                <flux:pillbox.option value="committees">{{ __('oidc_clients.scope_committees') }}</flux:pillbox.option>
                <flux:pillbox.option value="groups">{{ __('oidc_clients.scope_groups') }}</flux:pillbox.option>
                <flux:pillbox.option value="users">{{ __('oidc_clients.scope_users') }}</flux:pillbox.option>
            </flux:pillbox>
        </flux:field>

        <flux:switch
            wire:model="requiresConsent"
            label="{{ __('oidc_clients.requires_consent') }}"
            description="{{ __('oidc_clients.requires_consent_description') }}"
        />

        <x-slot:abort_route>
            {{ route('realms.oidc-clients', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
