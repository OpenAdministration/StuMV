<flux:navbar class="flex h-[4rem] shrink-0 items-center gap-x-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 sm:gap-x-6 sm:px-6 lg:px-8 z-10 print:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="menu" />
    <div class="hidden md:inline">
        {{-- {{ Breadcrumbs::render(Route::current()->getName(), $routeParams)}} --}}
    </div>

    <div class="ml-auto flex justify-end items-center gap-2">
        @can('superadmin', \App\Models\User::class)
            <livewire:sync-ldap />
        @endcan

        <x-info />
        
        <flux:dropdown align="end">
            <flux:profile :chevron="false" size="xl" avatar:name="{{ auth()->user()->full_name }}" />
            <flux:navmenu class="max-w-[20rem]">
                <div class="px-2 py-1.5">
                    <flux:heading size="lg" class="truncate">{{ auth()->user()->full_name }}</flux:heading>
                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                </div>
                <flux:navmenu.separator />
                <flux:navmenu.item
                    wire:navigate
                    :href="route('profile', auth()->user()->username)"
                    icon="circle-user"
                >
                    {{ __('Profile') }}
                </flux:navmenu.item>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:navmenu.item
                        :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                        icon="log-out"
                    >
                        {{ __('Log out') }}
                    </flux:navmenu.item>
                </form>
            </flux:navmenu>
        </flux:dropdown>
    </div>
</flux:navbar>