{{-- See 404.blade.php's comment for why both conditions are needed. --}}
@php
    $routeRealm = \Illuminate\Support\Facades\Route::current()?->parameter('realm');
    $canUseAppLayout = auth()->check()
        && (! $routeRealm || $routeRealm instanceof \App\Ldap\Community);
@endphp
@if($canUseAppLayout)
    <x-app-layout :error-code="403">
        <div class="flex flex-col items-center text-center gap-4 max-w-md mx-auto py-16">
            <flux:icon.shield-exclamation class="size-12 text-zinc-400" />
            <flux:heading size="xl">{{ __('errors.403_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('errors.403_text') }}</flux:text>
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
                <flux:icon.shield-exclamation class="size-12 text-zinc-400" />
                <flux:heading size="xl">{{ __('errors.403_title') }}</flux:heading>
                <flux:text class="text-base">{{ __('errors.403_text') }}</flux:text>
                <flux:button variant="primary" href="{{ route('login') }}" wire:navigate>
                    {{ __('errors.back_to_login') }}
                </flux:button>
            </div>
        </x-auth-card>
    </x-guest-layout>
@endif
