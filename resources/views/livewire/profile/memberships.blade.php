<div>
    <x-navbar-profile :username="$currentUsername" />
    
    <div class="mt-6 space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div class="flex items-center">
                <flux:switch wire:model.change="showOnlyActive" label="{{ __('profile.showOnlyActiveMemberships') }}" align="left" />
            </div>
            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="exportPdf">{{ __('profile.exportAsPdf') }}</flux:button>
            </div>
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('profile.role') }}</flux:table.column>
                <flux:table.column>{{ __('profile.committee') }}</flux:table.column>
                <flux:table.column>{{ __('profile.from') }}</flux:table.column>
                <flux:table.column>{{ __('profile.until') }}</flux:table.column>
                <flux:table.column>{{ __('profile.decision') }}</flux:table.column>
                <flux:table.column>{{ __('profile.comment') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
            @forelse($memberships as $row)
                <flux:table.row>
                    <flux:table.cell>{{ $row['role']->getFirstAttribute('description') }}</flux:table.cell>
                    <flux:table.cell>{{ $row['role']->committee()->getFirstAttribute('description') }}</flux:table.cell>
                    <flux:table.cell>{{ \Carbon\Carbon::parse($row['from'])->format('Y-m-d') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($row['until'] != '')
                            {{ \Carbon\Carbon::parse($row['until'])->format('Y-m-d') }}
                        @else
                            {{ __('profile.today') }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ \Carbon\Carbon::parse($row['decided'])->format('Y-m-d') }}</flux:table.cell>
                    <flux:table.cell>{{ $row['comment'] }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <div class="flex justify-center item-center">
                            <span class="text-gray-400 text-xl py-2 font-medium">{{ __('groups.no_roles_found') }}</span>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
