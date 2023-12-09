<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="ml-2.5 shrink-0 flex items-center">
                    <a href="{{ route('realms.pick') }}" class="flex space-x-3 items-center">
                        <x-application-logo />
                        <span class="text-3xl font-extrabold text-gray-800 tracking-tighter">StuMV</span>
                    </a>
                </div>
                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                    @can('picked', \App\Ldap\Community::class)
                        <x-nav-link wire:navigate :active="request()->routeIs('realms.dashboard')"
                                    :href="route('realms.dashboard', ['uid' => $uid])"
                        >
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        <x-nav-link wire:navigate :active="request()->routeIs('committees.list')"
                                    :href="route('committees.list', ['uid' => $uid])"
                        >
                            {{ __('Committees') }}
                        </x-nav-link>
                    @endcan
                    @can('superadmin', \App\Models\User::class)
                        <x-nav-link wire:navigate :active="request()->routeIs('superadmins.list')"
                                    :href="route('superadmins.list')"
                        >
                            <x-fas-dragon/> {{ __('Superadmins') }}
                        </x-nav-link>
                        <x-nav-link :active="request()->routeIs('realms.pick')"
                                    :href="route('realms.pick')">
                            <x-fas-repeat/> {{ __('Change Realm') }}
                        </x-nav-link>
                    @endcan


                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                            <div>{{ Auth::user()->full_name }}</div>

                            <div class="ml-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')">{{ __('Profile') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('password.change')">{{ __('Change Password') }}</x-dropdown-link>

                        <hr class="h-px my-1 mx-1 bg-gray-200 border-0">

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                             onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @can('picked', \App\Ldap\Community::class)
                <x-responsive-nav-link wire:navigate :active="request()->routeIs('committees.list')"
                                       :href="route('committees.list', ['uid' => $uid])"
                >
                    {{ __('Committees') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link wire:navigate :active="request()->routeIs('realms.members')"
                                       :href="route('realms.members', ['uid' => $uid])"
                >
                    {{ __('Members') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link wire:navigate :active="request()->routeIs('realms.mods')"
                                       :href="route('realms.mods', ['uid' => $uid])"
                >
                    {{ __('Mods') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link wire:navigate :active="request()->routeIs('realms.admins')"
                                       :href="route('realms.admins', ['uid' => $uid])"
                >
                    {{ __('Admins') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link wire:navigate :active="request()->routeIs('realms.groups')"
                                       :href="route('realms.groups', ['uid' => $uid])"
                >
                    {{ __('Gruppen') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :active="request()->routeIs('realms.domains')"
                                       :href="route('realms.domains', ['uid' => $uid])">
                    {{ __('Domains') }}
                </x-responsive-nav-link>
            @endcan
            @can('superadmin', \App\Models\User::class)
                <x-responsive-nav-link wire:navigate :active="request()->routeIs('superadmins.list')"
                                       :href="route('superadmins.list')"
                >
                    <x-slot:icon><x-fas-dragon/></x-slot:icon>
                    {{ __('Superadmins') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :active="request()->routeIs('realms.pick')"
                                       :href="route('realms.pick')">
                    <x-slot:icon><x-fas-door-open/></x-slot:icon>
                    {{ __('Change Realm') }}
                </x-responsive-nav-link>
            @endcan


        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4 mb-3">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->full_name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>
            <x-responsive-nav-link :active="request()->routeIs('profile')"
                                   :href="route('profile')">
                {{ __('Profil') }}
            </x-responsive-nav-link>
            <div>
                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                                           onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
