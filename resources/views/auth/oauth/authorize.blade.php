@php
    $realm = $request->route('realm')->getShortCode();
    $branding = \App\Models\RealmBranding::forRealm($realm);
@endphp
<x-guest-layout :branding="$branding">
    <x-auth-card>
        <flux:card class="grid gap-4 w-full bg-zinc-50 dark:bg-zinc-800 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-sm">
            <x-auth-logo :branding="$branding" />

            <flux:heading size="xl">{{ __('') }}</flux:heading>

            <flux:text>{{ __('auth.authorize_access_notice') }}</flux:text>
            <flux:text>{{ $client->name }}</flux:text>

            @if(count($scopes) > 0)
                <div class="space-y-2">
                    <p class="font-semibold">{{ __('auth.authorize_permissions_notice') }}</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($scopes as $scope)
                            <li>{{ __('auth.scope_' . $scope->id) }}</li>
                        @endforeach
                    </ul>
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
