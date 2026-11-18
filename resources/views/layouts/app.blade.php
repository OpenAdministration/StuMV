<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' | ' . config('app.name') : config('app.name') }}</title>
        <link rel="icon" href="{{ asset('img/logo.svg') }}">

        @livewireStyles
        @fluxAppearance(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
        @vite('resources/css/app.css')
        @vite('resources/js/app.js')
    </head>
    <body class="flex w-full h-full">
        <x-navigation/>

        <div class="grid grid-rows-[auto_1fr] w-full h-full">
            @include('components.header')

            <main class="relative h-full flex-1 overflow-x-hidden overflow-y-auto">
                {{-- profile pages fill the height, only their content below the tabs scrolls --}}
                <div class="p-6 sm:px-8 sm:pb-8 [&>div]:mx-auto [&>div]:max-w-7xl has-[[data-profile-layout]]:flex has-[[data-profile-layout]]:flex-col has-[[data-profile-layout]]:h-full">
                    <x-alert/>
                    {{ $slot }}
                </div>
            </main>
        </div>

        @fluxScripts

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

    </body>
</html>
