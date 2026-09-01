<div>
    <div class="mb-8 space-y-4">
        <flux:heading size="xl">{{ __('tools.tools') }}</flux:heading>
        <flux:text class="text-base">{{ __('tools.dashboard_explanation') }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6 pb-6 sm:pb-8">
        <a
            wire:navigate
            href="{{ route('tools.compare-email-list', $uid) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.list-check class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('tools.compare_email_list_headline') }}</flux:heading>
                <flux:text class="mt-2">{{ __('tools.compare_email_list_explanation') }}</flux:text>
            </flux:card>
        </a>
        @if($unildapDataExists)
            <a
                wire:navigate
                href="{{ route('tools.import-user-uni-ldap', $uid) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.user-plus class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('tools.import_users_from_uni_ldap_headline') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('tools.import_users_from_uni_ldap_explanation') }}</flux:text>
                </flux:card>
            </a>
            <a
                wire:navigate
                href="{{ route('tools.users-not-in-uni-ldap', $uid) }}"
                class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
            >
                <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                    <flux:icon.user-search class="size-5" />
                </div>
                <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                    <flux:heading size="lg">{{ __('tools.users_not_in_uni_ldap_headline') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('tools.users_not_in_uni_ldap_explanation') }}</flux:text>
                </flux:card>
            </a>
        @endif
        <a
            wire:navigate
            href="{{ route('tools.unused-roles', $uid) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.list-x class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('tools.unused_roles_headline') }}</flux:heading>
                <flux:text class="mt-2">{{ __('tools.unused_roles_explanation') }}</flux:text>
            </flux:card>
        </a>
        <a
            wire:navigate
            href="{{ route('tools.invite-user', $uid) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.mail class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('tools.invite_user_headline') }}</flux:heading>
                <flux:text class="mt-2">{{ __('tools.invite_user_explanation') }}</flux:text>
            </flux:card>
        </a>
        <a
            wire:navigate
            href="{{ route('tools.invitations', $uid) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.list class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('tools.pending_invitations_headline') }}</flux:heading>
                <flux:text class="mt-2">{{ __('tools.pending_invitations_explanation') }}</flux:text>
            </flux:card>
        </a>
    </div>
</div>
