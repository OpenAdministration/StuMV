<div>
    <div class="mb-8">
        <flux:heading size="xl" class="mb-4">{{ __('realms.dashboard.headline', ['name' => $name]) }}</flux:heading>
        <flux:text class="text-base">{{ __('realms.dashboard.explanation', ['name' => $name]) }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6">
        <a
            wire:navigate
            href="{{ route('profile', auth()->user()->username) }}"
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
        <a
            wire:navigate
            href="{{ route('realms.edit', $uid) }}"
            aria-label="{{ __('realms.dashboard.realms_edit_headline', ['name' => $name]) }}"
            disabled="{{ auth()->user()->cannot('edit', $community) }}"
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
        <a
            wire:navigate
            href="{{ route('realms.groups', $uid) }}"
            aria-label="{{ __('realms.dashboard.groups_headline', ['name' => $name]) }}"
            disabled="{{ auth()->user()->cannot('viewAny', [\App\Ldap\Group::class, $community]) }}"
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
        <a
            wire:navigate
            href="{{ route('realms.domains', $uid) }}"
            aria-label="{{ __('realms.dashboard.domains_headline', ['name' => $name]) }}"
            disabled="{{ auth()->user()->cannot('viewAny', [\App\Ldap\Domain::class, $community]) }}"
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
    </div>
</div>
