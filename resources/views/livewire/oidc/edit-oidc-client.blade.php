@if($regeneratedSecret)
    <div class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.edit_title') }}</flux:heading>
            <flux:callout
                variant="success"
                icon="circle-check"
                heading="{{ $regeneratedSecretReason === 'rotated' ? __('oidc_clients.secret_rotated_success') : __('oidc_clients.secret_regenerated_success') }}"
            />
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
        <x-livewire-form class="space-y-8 pb-8">
            <div>
                <flux:heading size="xl" class="mb-4">{{ __('oidc_clients.edit_title') }}</flux:heading>
                <flux:text class="text-base">{{ __('oidc_clients.explanation') }}</flux:text>
            </div>

            <flux:tab.group>
                <flux:tabs>
                    <flux:tab name="general">{{ __('oidc_clients.tab_general') }}</flux:tab>
                    <flux:tab name="uris">{{ __('oidc_clients.tab_uris') }}</flux:tab>
                    <flux:tab name="security">{{ __('oidc_clients.tab_security') }}</flux:tab>
                    <flux:tab name="service-provider">{{ __('oidc_clients.tab_service_provider') }}</flux:tab>
                </flux:tabs>

                <flux:tab.panel name="general" class="pt-8 space-y-6">
                    <flux:field>
                        <flux:label>{{ __('oidc_clients.name') }}</flux:label>
                        <flux:input wire:model="name" placeholder="{{ __('oidc_clients.name_placeholder') }}" />
                    </flux:field>

                    <flux:separator />

                    <flux:field>
                        <flux:label>{{ __('oidc_clients.description') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.description_description') }}</flux:description>
                        <flux:textarea wire:model="description" rows="3" />
                    </flux:field>

                    <flux:separator />

                    <livewire:oidc.edit-oidc-client-logo :client-id="$clientId" :realm-uid="$uid" :key="'logo-'.$clientId" />
                </flux:tab.panel>

                <flux:tab.panel name="uris" class="pt-8 space-y-6">
                    <flux:field>
                        <flux:label>{{ __('oidc_clients.redirect_uris') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.redirect_uris_description') }}</flux:description>
                        <flux:textarea wire:model="redirectUris" rows="4" placeholder="https://app.example.com/auth/callback" />
                    </flux:field>

                    <flux:separator />

                    <flux:field>
                        <flux:label>{{ __('oidc_clients.back_channel_logout_uri') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.back_channel_logout_uri_description') }}</flux:description>
                        <flux:input wire:model="backChannelLogoutUri" placeholder="https://app.example.com/logout-callback" />
                    </flux:field>

                    <flux:separator />

                    <flux:field>
                        <flux:label>{{ __('oidc_clients.post_logout_redirect_uris') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.post_logout_redirect_uris_description') }}</flux:description>
                        <flux:textarea wire:model="postLogoutRedirectUris" rows="4" placeholder="https://app.example.com/logged-out" />
                    </flux:field>
                </flux:tab.panel>

                <flux:tab.panel name="security" class="pt-8 space-y-6">
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

                    <flux:separator />

                    <flux:switch
                        wire:model="requiresConsent"
                        label="{{ __('oidc_clients.requires_consent') }}"
                        description="{{ __('oidc_clients.requires_consent_description') }}"
                    />

                    <flux:separator />

                    <flux:switch
                        wire:model="disableClientAuthentication"
                        label="{{ __('oidc_clients.disable_client_authentication') }}"
                        description="{{ __('oidc_clients.disable_client_authentication_description') }}"
                    />

                    @if(! $disableClientAuthentication)
                        <flux:separator />

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <flux:label>{{ __('oidc_clients.regenerate_secret') }}</flux:label>
                                <flux:description>{{ __('oidc_clients.regenerate_secret_description') }}</flux:description>
                            </div>
                            <flux:button icon="arrow-path" wire:click="confirmRegenerateSecret">
                                {{ __('oidc_clients.regenerate_secret_button') }}
                            </flux:button>
                        </div>
                    @endif
                </flux:tab.panel>

                <flux:tab.panel name="service-provider" class="pt-8 space-y-6">
                    <flux:field>
                        <flux:label>{{ __('oidc_clients.service_provider') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.service_provider_description') }}</flux:description>
                        <flux:input wire:model="serviceProvider" placeholder="{{ __('oidc_clients.service_provider_placeholder') }}" />
                    </flux:field>

                    <flux:separator />

                    <flux:field>
                        <flux:label>{{ __('oidc_clients.imprint_url') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.imprint_url_description') }}</flux:description>
                        <flux:input wire:model="imprintUrl" placeholder="https://example.com/imprint" />
                    </flux:field>

                    <flux:separator />

                    <flux:field>
                        <flux:label>{{ __('oidc_clients.terms_url') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.terms_url_description') }}</flux:description>
                        <flux:input wire:model="termsUrl" placeholder="https://example.com/terms" />
                    </flux:field>

                    <flux:separator />

                    <flux:field>
                        <flux:label>{{ __('oidc_clients.privacy_policy_url') }}</flux:label>
                        <flux:description>{{ __('oidc_clients.privacy_policy_url_description') }}</flux:description>
                        <flux:input wire:model="privacyPolicyUrl" placeholder="https://example.com/privacy" />
                    </flux:field>
                </flux:tab.panel>
            </flux:tab.group>

            <x-slot:abort_route>
                {{ route('realms.oidc-clients', ['realm' => $uid]) }}
            </x-slot:abort_route>
        </x-livewire-form>

        <form wire:submit="regenerateSecret">
            <flux:modal name="regenerate-secret">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg" class="modal-header">{{ __('oidc_clients.regenerate_secret_confirm_title') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('oidc_clients.regenerate_secret_confirm_warning') }}</flux:text>
                    </div>
                    <div class="flex justify-end gap-4">
                        <flux:button wire:click="closeRegenerateSecretModal">{{ __('common.cancel') }}</flux:button>
                        <flux:button variant="danger" type="submit">{{ __('oidc_clients.regenerate_secret_button') }}</flux:button>
                    </div>
                </div>
            </flux:modal>
        </form>
    </div>
@endif
