<flux:sidebar collapsible="mobile" class="w-[22rem]! p-0! flex flex-col gap-0! h-full grow bg-zinc-100 dark:bg-zinc-800">
    <flux:sidebar.header class="flex h-[4rem] px-6 shrink-0 items-center bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 border-r lg:border-r-0 border-r-zinc-300  dark:border-r-zinc-700 z-10">
        <a
            wire:navigate
            href="{{ \App\Providers\RouteServiceProvider::home($uid) }}"
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
                :href="route('realms.dashboard', ['uid' => $uid])"
            >
                {{ __('Dashboard') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="network"
                wire:navigate
                :href="route('committees.list', ['uid' => $uid])"
            >
                {{ __('Committees and Roles') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="users"
                wire:navigate
                :href="route('realms.members', ['uid' => $uid])"
            >
                {{ __('People') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="user-star"
                wire:navigate
                :href="route('realms.mods', ['uid' => $uid])"
            >
                {{ __('realms.dashboard.mods_headline') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="shield-user"
                wire:navigate
                :href="route('realms.admins', ['uid' => $uid])"
            >
                {{ __('realms.dashboard.admin_headline') }}
            </flux:sidebar.item>
            <flux:sidebar.item
                icon="key-round"
                wire:navigate
                :href="route('realms.groups', ['uid' => $uid])"
            >
                {{ __('realms.dashboard.groups_headline') }}
            </flux:sidebar.item>
            @can('moderator', [\App\Models\User::class, \App\Ldap\Community::class])
                <flux:separator class="my-2" />
                <flux:sidebar.item
                    icon="hammer"
                    wire:navigate
                    :href="route('tools.dashboard', ['uid' => $uid])"
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
                icon="log-in"
                wire:navigate
                :href="route('realms.pick')"
            >
                {{ __('Change Realm') }}
            </flux:sidebar.item>
        @endcan
    </flux:sidebar.nav>
</flux:sidebar>
