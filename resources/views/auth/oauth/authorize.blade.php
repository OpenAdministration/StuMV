@php
    $realm = $request->route('realm')->getShortCode();
    $branding = \App\Models\RealmBranding::forRealm($realm);
@endphp
<x-guest-layout :branding="$branding">
    <x-auth-card>
        <flux:card class="p-0 w-full bg-zinc-50 dark:bg-zinc-800 max-w-[40rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="p-6">
                <x-auth-logo :branding="$branding" />
            </div>

            <div class="p-6 space-y-4">
                <flux:heading size="xl">{{ __('auth.authorize_heading') }}</flux:heading>

                <p>{{ __('auth.authorize_access_notice') }}</p>

                <flux:card class="p-4 bg-zinc-50 dark:bg-zinc-900 shadow-xs space-y-4">
                    @if($client->logo_id)
                        <img class="w-full h-18 shrink-0 object-contain object-center" src="{{ asset('storage/oidc-client-logos/'.$client->logo_id) }}" alt="{{ $client->name }}">
                    @endif
                    <div class="space-y-1">
                        <p class="font-semibold text-xl">{{ $client->name }}</p>
                        @if($client->description)
                            <p class="text-sm">{{ $client->description }}</p>
                        @endif
                    </div>
                    @if($client->service_provider)
                        <p>{{ $client->service_provider }}</p>
                    @endif
                    @if($client->imprint_url || $client->terms_url || $client->privacy_policy_url)
                        <div class="flex flex-wrap gap-2">
                            @if($client->imprint_url)
                                <flux:button size="sm" target="_blank" icon="external-link" :href="$client->imprint_url">{{ __('oidc_clients.imprint_url') }}</flux:button>
                            @endif
                            @if($client->terms_url)
                                <flux:button size="sm" target="_blank" icon="external-link" :href="$client->terms_url">{{ __('oidc_clients.terms_url') }}</flux:button>
                            @endif
                            @if($client->privacy_policy_url)
                                <flux:button size="sm" target="_blank" icon="external-link" :href="$client->privacy_policy_url">{{ __('oidc_clients.privacy_policy_url') }}</flux:button>
                            @endif
                        </div>
                    @endif
                </flux:card>

                @php
                    // Do not show openid scope
                    $visibleScopes = collect($scopes)->reject(fn ($scope) => $scope->id === 'openid')->values();
                @endphp
                @if($visibleScopes->count() > 0)
                    @php
                        $scopeData = app(\App\Services\Oidc\ConsentScopeSummary::class)->forScopes($user, $visibleScopes->all());
                    @endphp
                    <div class="space-y-4 mt-2">
                        <p>{{ __('auth.authorize_permissions_notice') }}</p>
                        <div class="space-y-4">
                            @foreach ($visibleScopes as $scope)
                                <flux:fieldset>
                                    <legend>{{ __('auth.scope_' . $scope->id) }}</legend>
                                    <div class="p-4">
                                        @if(count($scopeData[$scope->id] ?? []) > 0)
                                            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                                @foreach ($scopeData[$scope->id] as $row)
                                                    <div class="grid sm:grid-cols-[8rem_1fr] gap-x-4 gap-y-1 items-center py-2 first:pt-0 last:pb-0">
                                                        <div class="font-semibold whitespace-nowrap">{{ $row['label'] }}</div>
                                                        <div>
                                                            @if($row['image'])
                                                                <flux:avatar size="lg" src="{{ $row['value'] }}" alt="{{ $row['label'] }}" />
                                                            @else
                                                                {{ $row['value'] }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            {{ __('auth.scope_' . $scope->id . '_detail') }}
                                        @endif
                                    </div>
                                </flux:fieldset>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="p-6 flex justify-end gap-2">
                <form method="POST" action="{{ route('realm.passport.authorizations.deny', ['realm' => $realm]) }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <flux:button icon="ban" type="submit">{{ __('auth.authorize_reject') }}</flux:button>
                </form>

                <form method="POST" action="{{ route('realm.passport.authorizations.approve', ['realm' => $realm]) }}">
                    @csrf
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <flux:button variant="primary" icon="check" type="submit">{{ __('auth.authorize_accept') }}</flux:button>
                </form>
            </div>
        </flux:card>
    </x-auth-card>
</x-guest-layout>
