<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $headline ?? '' }}</title>

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @livewireStyles
        <!-- Scripts -->
        <script src="{{ asset('js/app.js') }}" defer></script>
        @livewireScripts
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation', ['navigation' =>
                array_merge([
                    'dashboard' => __('Dashboard'),
                    'committees.list' => __('Committees'),
                    //'user' => __('User Management'),
                    'realms.groups' => __('Groups'),
                ],
                /*(Auth::user()->can('viewAny', App\Models\Committee::class)) ? [
                    'committees.list' => __('Committees'),
                ] : [],*/
                (Auth::user()->is_superuser) ? [
                    'realms.pick' => 'Realms',
                ] : []
                )
            ])

            <!-- Page Heading -->
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ $headline ?? '' }}
                    </h2>
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <div class="py-12">
                    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 bg-white border-b border-gray-200">
                                {{ Breadcrumbs::render(Route::current()->getName(), Route::current()->parameters())}}
                                <x-alert/>
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
