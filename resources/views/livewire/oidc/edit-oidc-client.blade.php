@if($regeneratedSecret)
    <div class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.edit_title') }}</flux:heading>
            <flux:callout variant="success" icon="circle-check" heading="{{ __('oidc_clients.secret_regenerated_success') }}" />
        </div>

        <flux:field>
            <flux:label>{{ __('oidc_clients.client_secret') }}</flux:label>
            <flux:input readonly copyable value="{{ $regeneratedSecret }}" />
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
                <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.edit_title') }}</flux:heading>
                <flux:text class="text-base">{{ __('oidc_clients.explanation') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('oidc_clients.name') }}</flux:label>
                <flux:input wire:model="name" placeholder="{{ __('oidc_clients.name_placeholder') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('oidc_clients.description') }}</flux:label>
                <flux:description>{{ __('oidc_clients.description_description') }}</flux:description>
                <flux:textarea wire:model="description" rows="3" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('oidc_clients.service_provider') }}</flux:label>
                <flux:description>{{ __('oidc_clients.service_provider_description') }}</flux:description>
                <flux:input wire:model="serviceProvider" placeholder="{{ __('oidc_clients.service_provider_placeholder') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('oidc_clients.logo') }}</flux:label>
                <flux:description>{{ __('oidc_clients.logo_description') }}</flux:description>
                @if($logoId)
                    <div class="flex items-center gap-4">
                        <img class="w-24 h-24 p-2 object-contain rounded-md border border-zinc-200 dark:border-zinc-700" src="{{ asset('storage/oidc-client-logos/'.$logoId) }}" alt="{{ __('oidc_clients.logo') }}">
                        <flux:button variant="danger" icon="trash-2" wire:click="removeLogo">{{ __('oidc_clients.remove_logo') }}</flux:button>
                    </div>
                @else
                    <flux:file-upload wire:model="logo" accept="image/*">
                        <flux:file-upload.dropzone
                            :heading="__('common.drop_file_here')"
                            text="JPEG, PNG, WebP, SVG"
                        />
                    </flux:file-upload>
                @endif
                <flux:error name="logo" />
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
                </flux:pillbox>
            </flux:field>

            <flux:switch
                wire:model="requiresConsent"
                label="{{ __('oidc_clients.requires_consent') }}"
                description="{{ __('oidc_clients.requires_consent_description') }}"
            />

            <flux:switch
                wire:model="disableClientAuthentication"
                label="{{ __('oidc_clients.disable_client_authentication') }}"
                description="{{ __('oidc_clients.disable_client_authentication_description') }}"
            />

            <flux:field>
                <flux:label>{{ __('oidc_clients.back_channel_logout_uri') }}</flux:label>
                <flux:description>{{ __('oidc_clients.back_channel_logout_uri_description') }}</flux:description>
                <flux:input wire:model="backChannelLogoutUri" placeholder="https://app.example.com/logout-callback" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('oidc_clients.post_logout_redirect_uris') }}</flux:label>
                <flux:description>{{ __('oidc_clients.post_logout_redirect_uris_description') }}</flux:description>
                <flux:textarea wire:model="postLogoutRedirectUris" rows="4" placeholder="https://app.example.com/logged-out" />
            </flux:field>

            <x-slot:abort_route>
                {{ route('realms.oidc-clients', ['realm' => $uid]) }}
            </x-slot:abort_route>
        </x-livewire-form>
    </div>
@endif
