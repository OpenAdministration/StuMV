<flux:navbar class="flex h-[4rem] min-w-0 shrink-0 items-center gap-x-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 px-4 sm:gap-x-6 sm:px-6 lg:px-8 z-10 print:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="menu" />
    <div class="md:flex min-w-0 flex-1">
        @php
            // Resolve route params here so the breadcrumbs work regardless of how
            // the layout passes data down. LDAP Entry bindings are reduced to their
            // route key (e.g. a Community -> its "ou"), matching AppLayout.
            $routeParams = collect(Route::current()?->parameters() ?? [])
                ->map(fn ($p) => $p instanceof \LdapRecord\Models\OpenLDAP\Entry
                    ? $p->getFirstAttribute($p->getRouteKeyName())
                    : $p)
                ->all();
        @endphp
        {{ Breadcrumbs::render(Route::current()->getName(), $routeParams) }}
    </div>

    <div class="ml-auto flex justify-end items-center gap-2">
        @can('superadmin', \App\Models\User::class)
            <livewire:sync-ldap />
        @endcan
        
        <flux:dropdown align="end">
            @php
                $jpegPhoto = \App\Ldap\User::findOrFailByUsername(auth()->user()->username)->getFirstAttribute('jpegPhoto');
                if ($jpegPhoto) {
                    $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                }
            @endphp
            <flux:profile :chevron="false" size="xl" avatar="{{ $jpegPhoto }}" avatar:name="{{ auth()->user()->full_name }}" />
            <flux:navmenu class="max-w-[20rem]">
                <flux:navmenu.item
                    wire:navigate
                    :href="route('profile', auth()->user()->username)"
                >
                    <flux:heading size="lg" class="truncate">{{ auth()->user()->full_name }}</flux:heading>
                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                </flux:navmenu.item>
                <flux:navmenu.separator />
                <x-info />
                <flux:navmenu.item
                    wire:navigate
                    :href="route('documentation')"
                    icon="book"
                >
                    {{ __('common.footer_documentation') }}
                </flux:navmenu.item>
                <flux:navmenu.separator />
                <flux:navmenu.item
                    wire:navigate
                    :href="route('about')"
                    icon="circle-user"
                >
                    {{ __('common.footer_about') }}
                </flux:navmenu.item>
                <flux:navmenu.item
                    wire:navigate
                    :href="route('privacy')"
                    icon="circle-user"
                >
                    {{ __('common.footer_privacy') }}
                </flux:navmenu.item>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:navmenu.item
                        :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                        icon="log-out"
                    >
                        {{ __('auth.log_out') }}
                    </flux:navmenu.item>
                </form>
            </flux:navmenu>
        </flux:dropdown>
    </div>
</flux:navbar>