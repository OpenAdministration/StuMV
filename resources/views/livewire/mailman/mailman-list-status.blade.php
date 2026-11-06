<div>
    <flux:tooltip :content="$text">
        @switch($state)
            @case('synced')
                <flux:icon.circle-check variant="mini" class="text-green-600 dark:text-green-400" />
                @break
            @case('out_of_sync')
                <flux:icon.circle-alert variant="mini" class="text-yellow-500 dark:text-yellow-400" />
                @break
            @default
                <flux:icon.circle-alert variant="mini" class="text-red-600 dark:text-red-400" />
        @endswitch
    </flux:tooltip>
    <span class="sr-only">{{ $text }}</span>
</div>
