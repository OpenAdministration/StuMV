<div>
    <div class="mb-8 space-y-4">
        <flux:heading size="xl">{{ __('tools.dashboard_headline') }}</flux:heading>
        <flux:text class="text-base">{{ __('tools.dashboard_explanation') }}</flux:text>
    </div>
    <div class="grid md:grid-cols-2 gap-6 pb-6 sm:pb-8">
        <a
            wire:navigate
            href="{{ route('tools.compare-email-list', $uid) }}"
            class="flex hover:ring-2 focus:ring-2 ring-(--color-accent-content) rounded-lg"
        >
            <div class="pt-4 px-3 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-(--color-accent-content) rounded-l-lg">
                <flux:icon.search class="size-5" />
            </div>
            <flux:card size="sm" class="flex-1 rounded-l-none border-l-0 p-3">
                <flux:heading size="lg">{{ __('tools.compareEmailList_headline') }}</flux:heading>
                <flux:text class="mt-2">{{ __('tools.compareEmailList_explanation') }}</flux:text>
            </flux:card>
        </a>
    </div>
</div>
