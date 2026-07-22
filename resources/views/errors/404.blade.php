@php
    // Two things have to hold before the rich, navigation-carrying layout is
    // safe to render for an error page:
    // - auth()->check(): components/header.blade.php calls auth()->user()->
    //   ... unconditionally, with no guard of its own. Route::current()
    //   being null (a URL matching no route at all, e.g. a mistyped path)
    //   is fine on its own now - App\View\Components\AppLayout and
    //   components/header.blade.php both tolerate that.
    // - the "realm" route parameter, if the matched route has one, is
    //   already a resolved Community - not the raw route segment string,
    //   which is exactly what it still is when the realm binding itself is
    //   what failed (a 404 for a URL with a nonexistent realm slug).
    //   components/navigation.blade.php and components/header.blade.php
    //   both call methods on it unconditionally (isAdminRealm(),
    //   getShortCode(), ...) with no type guard, so a plain string there
    //   crashes them.
    $routeRealm = \Illuminate\Support\Facades\Route::current()?->parameter('realm');
    $canUseAppLayout = auth()->check()
        && (! $routeRealm || $routeRealm instanceof \App\Ldap\Community);
@endphp
@if($canUseAppLayout)
    <x-app-layout :error-code="404">
        <div class="flex flex-col items-center text-center gap-4 max-w-md mx-auto py-16">
            <flux:icon.magnifying-glass class="size-12 text-zinc-400" />
            <flux:heading size="xl">{{ __('errors.404_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('errors.404_text') }}</flux:text>
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
                <flux:icon.magnifying-glass class="size-12 text-zinc-400" />
                <flux:heading size="xl">{{ __('errors.404_title') }}</flux:heading>
                <flux:text class="text-base">{{ __('errors.404_text') }}</flux:text>
                <flux:button variant="primary" href="{{ route('login') }}" wire:navigate>
                    {{ __('errors.back_to_login') }}
                </flux:button>
            </div>
        </x-auth-card>
    </x-guest-layout>
@endif
