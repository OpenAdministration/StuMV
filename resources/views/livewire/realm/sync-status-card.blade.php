<div class="h-full">
    <flux:card class="h-full flex items-start gap-4 p-4">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
            <flux:icon :icon="$icon" class="size-5 text-(--color-accent-content)" />
        </div>
        <div class="flex-1 min-w-0">
            <flux:heading size="lg">{{ $heading }}</flux:heading>
            <div class="mt-1 flex items-center gap-2">
                @switch($state)
                    @case('loading')
                        <flux:icon.loading variant="mini" class="text-zinc-400" />
                        @break
                    @case('synced')
                        <flux:icon.circle-check variant="mini" class="shrink-0 text-green-600 dark:text-green-400" />
                        @break
                    @case('pending')
                        <flux:icon.circle-alert variant="mini" class="shrink-0 text-yellow-500 dark:text-yellow-400" />
                        @break
                    @default
                        <flux:icon.circle-alert variant="mini" class="shrink-0 text-red-600 dark:text-red-400" />
                @endswitch
                @if($text)
                    <flux:text>{{ $text }}</flux:text>
                @endif
            </div>
            @if($ldapAction ?? false)
                <flux:button size="sm" class="mt-3" x-on:click="$dispatch('open-ldap-sync-preview')">{{ __('sync.status_review') }}</flux:button>
            @elseif(isset($href) && $state !== 'synced')
                <flux:button size="sm" class="mt-3" wire:navigate :href="$href">{{ __('sync.status_open_lists') }}</flux:button>
            @endif
        </div>
    </flux:card>
</div>
