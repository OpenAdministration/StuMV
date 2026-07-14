<div class="flex-col space-y-8">
    <div>
        <flux:heading size="xl" class="mb-4">{{ __('groups.members_headline', ['name' => $group_cn]) }}</flux:heading>
        <flux:text class="text-base">{{ __('groups.members_explanation') }}</flux:text>
    </div>

    <flux:field>
        <flux:label>{{ __('groups.members.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div class="pb-6 sm:pb-8">
        @if(count($members) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-[55px]"></flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'cn'" :direction="$sortDirection" wire:click="sortBy('cn')">{{ __('User') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($members as $row)
                    @php
                        $user = $row['user'];
                        $jpegPhoto = $user->getFirstAttribute('jpegPhoto');
                        if ($jpegPhoto) {
                            $jpegPhoto = 'data:image/jpeg;base64,' . $jpegPhoto;
                        }
                        $displayName = $user->getFirstAttribute('cn') ?? $user->getFirstAttribute('uid');
                        $statusColor = match ($row['status']) {
                            'synced' => 'green',
                            'pending' => 'yellow',
                            default => 'red',
                        };
                    @endphp
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:avatar
                                badge badge:color="{{ $statusColor }}"
                                src="{{ $jpegPhoto }}"
                                name="{{ $displayName }}"
                            />
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link
                                wire:navigate
                                href="{{ route('profile', ['username' => $user->getFirstAttribute('uid')]) }}"
                            >
                                {{ $displayName }}
                            </flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pagination">
                <flux:pagination :paginator="$members" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('groups.no_members_found') }}" />
        @endif
    </div>
</div>
