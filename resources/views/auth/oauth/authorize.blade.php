@php
    $realm = $request->route('realm')->getShortCode();
    $branding = \App\Models\RealmBranding::forRealm($realm);
@endphp
<x-guest-layout :branding="$branding">
    <x-auth-card>
        <flux:card class="grid gap-4 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark:bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-sm">
            <x-auth-logo :branding="$branding" />

            <flux:heading size="xl">{{ __('auth.authorize_heading') }}</flux:heading>

            <div class="space-y-4">
                <p>{{ __('auth.authorize_access_notice') }}</p>
                @if($client->logo_id)
                    <img class="w-full h-12 shrink-0 object-contain object-center" src="{{ asset('storage/oidc-client-logos/'.$client->logo_id) }}" alt="{{ $client->name }}">
                @endif
                <div class="space-y-2">
                    <p class="font-semibold text-xl">{{ $client->name }}</p>
                    @if($client->service_provider)
                        <p class="text-sm">{{ $client->service_provider }}</p>
                    @endif
                </div>
                @if($client->description)
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $client->description }}</p>
                @endif
            </div>

            @if(count($scopes) > 0)
                @php
                    $scopeData = app(\App\Services\Oidc\ConsentScopeSummary::class)->forScopes($user, $scopes);
                @endphp
                <div class="space-y-4">
                    <p>{{ __('auth.authorize_permissions_notice') }}</p>
                    <div class="space-y-4">
                        @foreach ($scopes as $scope)
                            <flux:fieldset>
                                <legend>{{ __('auth.scope_' . $scope->id) }}</legend>
                                <div class="p-4">
                                    @if(count($scopeData[$scope->id] ?? []) > 0)
                                        <div class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 items-center">
                                            @foreach ($scopeData[$scope->id] as $row)
                                                <div class="font-semibold whitespace-nowrap">{{ $row['label'] }}</div>
                                                <div>
                                                    @if($row['image'])
                                                        <flux:avatar size="lg" src="{{ $row['value'] }}" alt="{{ $row['label'] }}" />
                                                    @else
                                                        {{ $row['value'] }}
                                                    @endif
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

            <div class="flex justify-end gap-2">
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
