<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __($title ?? '') }}</title>
    <link rel="icon" href="{{ asset('img/logo.svg') }}">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    @livewireScripts
</head>
<body class="font-sans antialiased">
<div class="min-h-screen bg-gray-100 flex flex-col justify-between">
    <div>
        <x-navigation/>
        <!-- Page Heading -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight pl-3">
                    {{ Breadcrumbs::render(Route::current()->getName(), $routeParams)}}
                </h2>
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <x-alert/>
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    @include('layouts.footer')
</div>
</body>
</html>
