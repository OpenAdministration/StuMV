<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="{{ asset('img/logo.svg') }}">
        <title>{{ isset($title) ? $title . ' | ' . config('app.name') : config('app.name') }}</title>

        @livewireStyles
        @fluxAppearance(['nonce' => \Illuminate\Support\Facades\Vite::cspNonce()])
        @vite('resources/css/app.css')
        @vite('resources/css/theme.css')
        @vite('resources/js/app.js')
        @if(($branding ?? null)?->background_id)
            <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
                body {
                    background-image: url('{{ asset('storage/realm-branding/'.$branding->background_id) }}');
                }
            </style>
        @endif
    </head>
    <body class="flex flex-col h-full bg-zinc-100! dark:bg-zinc-900! bg-cover bg-center bg-fixed overflow-y-auto">
        <main class="flex-1 flex flex-col my-auto px-4 pt-4 sm:pt-8 pb-8 overflow-y-visible">
            {{ $slot }}
        </main>
        @include('layouts.footer')
        @fluxScripts
    </body>
</html>
