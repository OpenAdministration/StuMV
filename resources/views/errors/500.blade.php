@php
    // Same two conditions as 404.blade.php/403.blade.php (see there for why
    // each is needed) - a 500 can additionally mean the DB/LDAP itself is
    // unhealthy, which auth()->check() and the app layout's own queries
    // could then fail on too. That's still safe to attempt: if rendering
    // this view throws, Laravel's Handler::renderHttpException() catches it,
    // reports it, and falls back to a plain (but functional) generic error
    // response instead of looping - it does not call back into this view.
    $routeRealm = \Illuminate\Support\Facades\Route::current()?->parameter('realm');
    $canUseAppLayout = auth()->check()
        && (! $routeRealm || $routeRealm instanceof \App\Ldap\Community);
@endphp
@if($canUseAppLayout)
    <x-app-layout :error-code="500">
        <div class="flex flex-col items-center text-center gap-4 max-w-md mx-auto py-16">
            <flux:icon.exclamation-triangle class="size-12 text-zinc-400" />
            <flux:heading size="xl">{{ __('errors.500_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('errors.500_text') }}</flux:text>
            <flux:button variant="primary" :href="\App\Providers\RouteServiceProvider::home()" wire:navigate>
                {{ __('errors.back_to_dashboard') }}
            </flux:button>
        </div>
    </x-app-layout>
@else
    <x-guest-layout>
        <x-auth-card>
            <x-application-logo class="w-20 h-20" />
            <div class="flex flex-col items-center text-center gap-4">
                <flux:icon.exclamation-triangle class="size-12 text-zinc-400" />
                <flux:heading size="xl">{{ __('errors.500_title') }}</flux:heading>
                <flux:text class="text-base">{{ __('errors.500_text') }}</flux:text>
                <flux:button variant="primary" href="{{ url('/') }}">
                    {{ __('errors.back_to_login') }}
                </flux:button>
            </div>
        </x-auth-card>
    </x-guest-layout>
@endif
