<div class="flex-col">
    <div class="mb-8">
        <flux:heading size="xl" class="mb-4">{{ __('group_mailman_lists.members_headline', ['name' => $mailmanListId]) }}</flux:heading>
        <flux:text class="text-base">{{ __('group_mailman_lists.members_explanation') }}</flux:text>
    </div>

    @if($fetchFailed)
        <flux:callout variant="danger" icon="circle-alert" heading="{{ __('group_mailman_lists.members_fetch_failed') }}" class="mb-8" />
    @endif

    <flux:field class="mb-8">
        <flux:label>{{ __('group_mailman_lists.members.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div class="pb-6 sm:pb-8">
        @if(count($members) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-[55px]"></flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'display_name'" :direction="$sortDirection" wire:click="sortBy('display_name')">{{ __('common.user') }}</flux:table.column>
                    <flux:table.column>{{ __('group_mailman_lists.members.email') }}</flux:table.column>
                    <flux:table.column>{{ __('group_mailman_lists.members.via_groups') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($members as $row)
                    @php
                        $user = $row['user'];
                        $jpegPhoto = $user?->getFirstAttribute('jpegPhoto');
                        if ($jpegPhoto) {
                            $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                        }
                        $statusColor = match ($row['status']) {
                            'synced' => 'green',
                            'pending' => 'yellow',
                            'stale' => 'red',
                            default => 'zinc',
                        };
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:avatar
                                badge badge:color="{{ $statusColor }}"
                                src="{{ $jpegPhoto }}"
                                name="{{ $row['display_name'] }}"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($user)
                                <flux:link
                                    wire:navigate
                                    href="{{ route('profile', ['realm' => $uid, 'username' => $user->getFirstAttribute('uid')]) }}"
                                >
                                    {{ $row['display_name'] }}
                                </flux:link>
                            @else
                                {{ $row['display_name'] }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs text-zinc-500">{{ $row['email'] }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if(count($row['groups']) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($row['groups'] as $groupCn)
                                        <flux:badge>{{ $groupCn }}</flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <flux:separator />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pagination">
                <flux:pagination :paginator="$members" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('group_mailman_lists.no_members_found') }}" />
        @endif
    </div>
</div>
