<flux:sidebar collapsible="mobile" class="w-[22rem]! p-0! flex flex-col gap-0! h-full grow bg-zinc-100 dark:bg-zinc-800">
    <flux:sidebar.header class="flex h-[4rem] px-6 shrink-0 items-center bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 border-r lg:border-r-0 border-r-zinc-300  dark:border-r-zinc-700 z-10">
        <a
            wire:navigate
            href="{{ \App\Providers\RouteServiceProvider::home($realm) }}"
            class="flex gap-3 items-center lg:justify-center pr-4 w-full"
        >
            <x-application-logo class="size-8"/>
            <span class="text-2xl font-bold">{{ config('app.name') }}</span>
        </a>
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <flux:sidebar.nav class="grow overflow-y-auto border-r border-zinc-200 dark:border-zinc-700 px-6 py-4">
        @can('picked', \App\Ldap\Community::class)
            <flux:sidebar.item
                icon="house"
                wire:navigate
                :href="route('realms.dashboard', ['realm' => $realm])"
            >
                {{ __('realms.nav_dashboard') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="list-tree"
                wire:navigate
                :href="route('committees.list', ['realm' => $realm])"
            >
                {{ __('realms.nav_committees_and_roles') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="users"
                wire:navigate
                :href="route('realms.members', ['realm' => $realm])"
            >
                {{ __('realms.nav_people') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="user-star"
                wire:navigate
                :href="route('realms.mods', ['realm' => $realm])"
            >
                {{ __('realms.dashboard.mods_headline') }}
            </flux:sidebar.item>
            @php($currentCommunity = \Illuminate\Support\Facades\Route::current()->parameter('realm'))
            @if(auth()->user()->can('moderator', $currentCommunity) || auth()->user()->can('admin', $currentCommunity) || auth()->user()->can('superadmin', \App\Models\User::class))
                <flux:sidebar.item
                    icon="shield-user"
                    wire:navigate
                    :href="route('realms.admins', ['realm' => $realm])"
                >
                    {{ __('realms.dashboard.admin_headline') }}
                </flux:sidebar.item>
            @endif
            @if(auth()->user()->can('admin', $currentCommunity) || auth()->user()->can('superadmin', \App\Models\User::class))
                <flux:sidebar.item
                    icon="key-round"
                    wire:navigate
                    :href="route('realms.groups', ['realm' => $realm])"
                >
                    {{ __('realms.dashboard.groups_headline') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="globe"
                    wire:navigate
                    :href="route('realms.domains', ['realm' => $realm])"
                >
                    {{ __('realms.dashboard.domains_headline') }}
                </flux:sidebar.item>
                <flux:sidebar.item
                    icon="unplug"
                    wire:navigate
                    :href="route('realms.api-clients', ['realm' => $realm])"
                >
                    {{ __('api_clients.list_title') }}
                </flux:sidebar.item>
            @endif
            @can('tools', \Illuminate\Support\Facades\Route::current()->parameter('realm'))
                <flux:separator class="my-2" />
                <flux:sidebar.item
                    icon="hammer"
                    wire:navigate
                    :href="route('tools.dashboard', ['realm' => $realm])"
                    :current="request()->is('*/tools') || request()->is('*/tools/*')"
                >
                    {{ __('tools.tools') }}
                </flux:sidebar.item>
            @endcan
        @endcan
        @can('superadmin', \App\Models\User::class)
            @can('picked', \App\Ldap\Community::class)
                <flux:separator class="my-2" />
            @endcan
            <flux:sidebar.item
                icon="squirrel"
                wire:navigate
                :href="route('superadmins.list')"
            >
                {{ __('Superadmins') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="network"
                wire:navigate
                :href="route('oidc-clients.list')"
            >
                {{ __('oidc_clients.list_title') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="log-in"
                wire:navigate
                :href="route('realms.pick')"
            >
                {{ __('realms.change_realm') }}
            </flux:sidebar.item>
        @endcan
    </flux:sidebar.nav>
</flux:sidebar>
