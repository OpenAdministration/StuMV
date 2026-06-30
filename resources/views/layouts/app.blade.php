<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title . ' | ' . config('app.name') ?? config('app.name') }}</title>
        <link rel="icon" href="{{ asset('img/logo.svg') }}">

        @livewireStyles
        @fluxAppearance
        @vite('resources/css/app.css')
        @vite('resources/css/theme.css')
        @vite('resources/js/app.js')
    </head>
    <body class="flex w-full h-full">
        <x-navigation/>

        <div class="grid grid-rows-[auto_1fr] w-full h-full">
            @include('components.header')

            <main class="h-full flex-1 overflow-x-hidden overflow-y-auto p-6 sm:p-8">
                <x-alert/>
                {{ $slot }}
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
