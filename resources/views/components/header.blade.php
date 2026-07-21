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
            <flux:profile
                :chevron="false"
                size="xl"
                avatar="{{ $jpegPhoto }}"
                avatar:name="{{ auth()->user()->full_name }}"
                title="{{ auth()->user()->full_name }}"
            />
            <flux:navmenu class="max-w-[20rem]">
                <flux:navmenu.item
                    icon="circle-user"
                    wire:navigate
                    :href="route('profile', ['realm' => auth()->user()->realm, 'username' => auth()->user()->username])"
                >
                    <div class="flex flex-col items-start">
                        <span class="font-bold">{{ auth()->user()->full_name }}</span>
                        <span class="text-xs opacity-70">{{ auth()->user()->email }}</span>
                    </div>
                </flux:navmenu.item>
                <flux:navmenu.separator />
                <x-info />
                <flux:navmenu.item
                    icon="notebook-text"
                    :href="route('documentation')"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ __('common.footer_documentation') }}
                </flux:navmenu.item>
                <flux:navmenu.separator />
                @if(config('app.imprint_url') !== '')
                    <flux:navmenu.item
                        icon="badge-info"
                        :href="route('imprint')"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('common.footer_imprint') }}
                    </flux:navmenu.item>
                @endif
                @if(config('app.terms_url') !== '')
                    <flux:navmenu.item
                        icon="section"
                        :href="route('terms')"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('common.footer_terms') }}
                    </flux:navmenu.item>
                @endif
                @if(config('app.privacy_url') !== '')
                    <flux:navmenu.item
                        icon="hat-glasses"
                        :href="route('privacy')"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        {{ __('common.footer_privacy') }}
                    </flux:navmenu.item>
                @endif
                <flux:navmenu.separator />
                @php($currentRealm = request()->route('realm'))
                <form method="POST" action="{{ $currentRealm ? route('realm.logout', ['realm' => $currentRealm->getShortCode()]) : route('logout') }}">
                    @csrf
                    <flux:navmenu.item
                        :href="$currentRealm ? route('realm.logout', ['realm' => $currentRealm->getShortCode()]) : route('logout')"
                        x-on:click.prevent="$el.closest('form').submit()"
                        icon="log-out"
                    >
                        {{ __('auth.log_out') }}
                    </flux:navmenu.item>
                </form>
            </flux:navmenu>
        </flux:dropdown>
    </div>
</flux:navbar>