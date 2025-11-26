<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('img/logo.svg') }}">
        <title>{{ config('app.name', 'Laravel') }}</title>

        @livewireStyles
        @fluxAppearance
        @vite('resources/css/app.css')
        @vite('resources/css/theme.css')
        @vite('resources/js/app.js')
    </head>
    <body class="flex flex-col h-full bg-zinc-100 dark:bg-zinc-900 overflow-y-auto">
        <main class="flex flex-col my-auto py-8 overflow-y-visible">
            {{ $slot }}
        </main>
        @include('layouts.footer')
        @fluxScripts
    </body>
</html>
