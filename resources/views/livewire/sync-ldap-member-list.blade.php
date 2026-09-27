@foreach($entry['additions'] as $member)
    <div class="flex items-center gap-2 py-2.5">
        <flux:icon name="user-plus" class="size-4 shrink-0 text-green-600 dark:text-green-400" />
        <span class="sr-only">{{ __('sync.member_added') }}</span>
        @if($member['username'])
            <flux:link href="{{ route('profile', ['realm' => $uid, 'username' => $member['username']]) }}" target="_blank" rel="noopener noreferrer">{{ $member['name'] }}</flux:link>
        @else
            {{ $member['name'] }}
        @endif
    </div>
@endforeach
@foreach($entry['removals'] as $member)
    <div class="flex items-center gap-2 py-2.5">
        <flux:icon name="user-minus" class="size-4 shrink-0 text-red-600 dark:text-red-400" />
        <span class="sr-only">{{ __('sync.member_removed') }}</span>
        @if($member['username'])
            <flux:link href="{{ route('profile', ['realm' => $uid, 'username' => $member['username']]) }}" target="_blank" rel="noopener noreferrer">{{ $member['name'] }}</flux:link>
        @else
            {{ $member['name'] }}
        @endif
    </div>
@endforeach
