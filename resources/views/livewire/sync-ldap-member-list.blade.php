<ul class="mt-1 space-y-1 text-sm">
    @foreach($entry['additions'] as $member)
        <li class="flex items-center gap-1.5 text-green-600 dark:text-green-400">
            <flux:icon name="user-plus" class="size-4 shrink-0 text-green-600 dark:text-green-400" />
            <span class="sr-only">{{ __('sync.member_added') }}</span>
            @if($member['username'])
                <flux:link href="{{ route('profile', ['realm' => $uid, 'username' => $member['username']]) }}" target="_blank" rel="noopener noreferrer">{{ $member['name'] }}</flux:link>
            @else
                {{ $member['name'] }}
            @endif
        </li>
    @endforeach
    @foreach($entry['removals'] as $member)
        <li class="flex items-center gap-1.5 text-red-600 dark:text-red-400">
            <flux:icon name="user-minus" class="size-4 shrink-0 text-red-600 dark:text-red-400" />
            <span class="sr-only">{{ __('sync.member_removed') }}</span>
            @if($member['username'])
                <flux:link href="{{ route('profile', ['realm' => $uid, 'username' => $member['username']]) }}" target="_blank" rel="noopener noreferrer">{{ $member['name'] }}</flux:link>
            @else
                {{ $member['name'] }}
            @endif
        </li>
    @endforeach
</ul>
