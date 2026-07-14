<x-guest-layout>
    <x-auth-card>
        <x-slot:slot class="space-y-5">
            <h2 class="font-bold text-gray-900 sm:truncate sm:tracking-tight">{{ __('Authorization Request') }}</h2>

            <p>
                {{ __(':client is requesting permission to access your account.', ['client' => $client->name]) }}
            </p>

            @if (count($scopes) > 0)
                <div class="space-y-2">
                    <p class="font-semibold">{{ __('This application will be able to:') }}</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($scopes as $scope)
                            <li>{{ $scope->description }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex justify-evenly">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <flux:button icon="ban" type="submit">{{ __('Cancel') }}</flux:button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <flux:button variant="primary" icon="check" type="submit">{{ __('Authorize') }}</flux:button>
                </form>
            </div>
        </x-slot:slot>
    </x-auth-card>
</x-guest-layout>
