<x-profile-layout :realm="$realm_uid" :username="$currentUsername" :name="$givenName.' '.$sn">
    <div class="max-w-7xl mx-auto space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div class="flex items-center">
                <flux:switch wire:model.live="showOnlyActive" label="{{ __('profile.show_only_active_memberships') }}" align="left" />
            </div>
            <div class="flex justify-end">
                <flux:button variant="primary" icon="file-text" wire:navigate :disabled="! $hasMemberships" :href="$hasMemberships ? route('profile.memberships.export', ['realm' => $realm_uid, 'username' => $currentUsername]) : null">{{ __('profile.export_as_pdf') }}</flux:button>
            </div>
        </div>
        @if(count($memberships) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'role'" :direction="$sortDirection" wire:click="sortBy('role')">{{ __('profile.role') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'committee'" :direction="$sortDirection" wire:click="sortBy('committee')">{{ __('profile.committee') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'from'" :direction="$sortDirection" wire:click="sortBy('from')">{{ __('profile.from') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'until'" :direction="$sortDirection" wire:click="sortBy('until')">{{ __('profile.until') }}</flux:table.column>
                    <flux:table.column>{{ __('profile.decision') }}</flux:table.column>
                    <flux:table.column>{{ __('profile.comment') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($memberships as $row)
                    <flux:table.row>
                        @php($committee = $row['role']->committee())
                        <flux:table.cell>
                            <flux:link wire:navigate :href="route('committees.roles.members', ['realm' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $row['role']->getFirstAttribute('cn')])">
                                {{ $row['role']->getFirstAttribute('description') }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link wire:navigate :href="route('committees.roles', ['realm' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')])">
                                {{ $committee->getFirstAttribute('description') }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ \Carbon\Carbon::parse($row['from'])->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">
                            @if ($row['until'] != '')
                                {{ \Carbon\Carbon::parse($row['until'])->format('Y-m-d') }}
                            @else
                                {{ __('profile.today') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ \Carbon\Carbon::parse($row['decided'])->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ $row['comment'] }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('profile.no_memberships_found') }}" />
        @endif
    </div>
</x-profile-layout>
