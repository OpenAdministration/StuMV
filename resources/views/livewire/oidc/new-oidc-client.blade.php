@if($createdClientSecret)
    <div class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.new_title') }}</flux:heading>
            <flux:callout variant="success" icon="circle-check" heading="{{ __('oidc_clients.created_success') }}" />
        </div>

        <flux:field>
            <flux:label>{{ __('oidc_clients.client_id') }}</flux:label>
            <flux:input readonly copyable value="{{ $createdClientId }}" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('oidc_clients.client_secret') }}</flux:label>
            <flux:input readonly copyable value="{{ $createdClientSecret }}" />
        </flux:field>

        <flux:callout variant="warning" icon="triangle-alert" heading="{{ __('oidc_clients.client_secret_warning') }}" />

        <div class="flex justify-end">
            <flux:button variant="primary" wire:navigate href="{{ route('realms.oidc-clients', ['realm' => $uid]) }}">
                {{ __('oidc_clients.done') }}
            </flux:button>
        </div>
    </div>
@else
    <div>
        <x-livewire-form class="space-y-8">
            <div>
                <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.new_title') }}</flux:heading>
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
                    <flux:pillbox.option value="groups">{{ __('oidc_clients.scope_groups') }}</flux:pillbox.option>
                    <flux:pillbox.option value="users">{{ __('oidc_clients.scope_users') }}</flux:pillbox.option>
                </flux:pillbox>
            </flux:field>

            <flux:switch
                wire:model="requiresConsent"
                label="{{ __('oidc_clients.requires_consent') }}"
                description="{{ __('oidc_clients.requires_consent_description') }}"
            />

            <flux:field>
                <flux:label>{{ __('oidc_clients.back_channel_logout_uri') }}</flux:label>
                <flux:description>{{ __('oidc_clients.back_channel_logout_uri_description') }}</flux:description>
                <flux:input wire:model="backChannelLogoutUri" placeholder="https://app.example.com/logout-callback" />
            </flux:field>

            <x-slot:abort_route>
                {{ route('realms.oidc-clients', ['realm' => $uid]) }}
            </x-slot:abort_route>
        </x-livewire-form>
    </div>
@endif
