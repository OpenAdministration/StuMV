<div>
    <div class="mb-8 space-y-4">
        <flux:heading size="xl">{{ __('realms.dashboard.headline', ['name' => $name]) }}</flux:heading>
        <flux:text class="text-base">{{ __('realms.dashboard.explanation', ['name' => $name]) }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6 pb-6 sm:pb-8">
        <a
            wire:navigate
            href="{{ route('profile', ['realm' => $uid, 'username' => auth()->user()->username]) }}"
            aria-label="{{ __('realms.dashboard.profile_heading', ['name' => $name]) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.circle-user class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('realms.dashboard.profile_heading', ['name' => $name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('realms.dashboard.profile_explanation', ['name' => $name]) }}</flux:text>
            </flux:card>
        </a>
        @unless($community->isAdminRealm())
            <a
                wire:navigate
                href="{{ route('committees.list', $uid) }}"
                aria-label="{{ __('realms.dashboard.committee_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.network class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.committee_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.committee_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endunless
        <a
            wire:navigate
            href="{{ route('realms.members', $uid) }}"
            aria-label="{{ __('realms.dashboard.members_heading', ['name' => $name]) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.users class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('realms.dashboard.members_heading', ['name' => $name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('realms.dashboard.members_explanation', ['name' => $name]) }}</flux:text>
            </flux:card>
        </a>
        @unless($community->isAdminRealm())
            <a
                wire:navigate
                href="{{ route('realms.mods', $uid) }}"
                aria-label="{{ __('realms.dashboard.mods_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.user-star class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.mods_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.mods_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endunless
        @if(! $community->isAdminRealm() && (auth()->user()->can('moderator', $community) || auth()->user()->can('admin', $community) || auth()->user()->can('superadmin', \App\Models\User::class)))
            <a
                wire:navigate
                href="{{ route('realms.admins', $uid) }}"
                aria-label="{{ __('realms.dashboard.admin_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.shield-user class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.admin_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.admin_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endif
        @can('edit', $community)
            <a
                wire:navigate
                href="{{ route('realms.edit', $uid) }}"
                aria-label="{{ __('realms.dashboard.realms_edit_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.landmark class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.realms_edit_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.realms_edit_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endcan
        @can('edit', $community)
            <a
                wire:navigate
                href="{{ route('realms.branding', $uid) }}"
                aria-label="{{ __('realms.dashboard.branding_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.palette class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.branding_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.branding_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endcan
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <a
                wire:navigate
                href="{{ route('realms.groups', $uid) }}"
                aria-label="{{ __('realms.dashboard.groups_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.key-round class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.groups_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.groups_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endif
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <a
                wire:navigate
                href="{{ route('realms.domains', $uid) }}"
                aria-label="{{ __('realms.dashboard.domains_headline', ['name' => $name]) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.globe class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('realms.dashboard.domains_headline', ['name' => $name]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.dashboard.domains_explanation', ['name' => $name]) }}</flux:text>
                </flux:card>
            </a>
        @endif
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <a
                wire:navigate
                href="{{ route('realms.api-clients', $uid) }}"
                aria-label="{{ __('api_clients.headline') }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.unplug class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('api_clients.headline') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('api_clients.explanation') }}</flux:text>
                </flux:card>
            </a>
        @endif
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <a
                wire:navigate
                href="{{ route('realms.oidc-clients', $uid) }}"
                aria-label="{{ __('oidc_clients.headline') }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.network class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('oidc_clients.headline') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('oidc_clients.explanation') }}</flux:text>
                </flux:card>
            </a>
        @endif
        @if(! $community->isAdminRealm() && auth()->user()->can('viewAny', [\App\Ldap\Group::class, $community]))
            <a
                wire:navigate
                href="{{ route('realms.sso-providers', $uid) }}"
                aria-label="{{ __('sso_providers.headline') }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.git-pull-request-create-arrow class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('sso_providers.headline') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('sso_providers.explanation') }}</flux:text>
                </flux:card>
            </a>
        @endif
    </div>
</div>
