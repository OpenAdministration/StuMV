<div class="max-w-7xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('profile.export_memberships_title') }}</flux:heading>
        <flux:text class="text-base">{{ $name }}</flux:text>
    </div>

    <form wire:submit="export" class="space-y-6 pb-8">
        @if(count($memberships) > 0)
            <div class="flex gap-2">
                <flux:button size="sm" icon="list-check" wire:click="selectAll">{{ __('profile.export_memberships_select_all') }}</flux:button>
                <flux:button size="sm" icon="list-x" wire:click="deselectAll">{{ __('profile.export_memberships_deselect_all') }}</flux:button>
            </div>
            <flux:checkbox.group wire:model="selected">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-0"><span class="sr-only">{{ __('profile.export_memberships_title') }}</span></flux:table.column>
                        <flux:table.column>{{ __('profile.role') }}</flux:table.column>
                        <flux:table.column>{{ __('profile.committee') }}</flux:table.column>
                        <flux:table.column>{{ __('profile.from') }}</flux:table.column>
                        <flux:table.column>{{ __('profile.until') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($memberships as $row)
                            <flux:table.row wire:key="membership-{{ $row['id'] }}">
                                <flux:table.cell>
                                    <flux:checkbox
                                        value="{{ $row['id'] }}"
                                        :aria-label="$row['role']->getFirstAttribute('description').' – '.$row['role']->committee()->getFirstAttribute('description')"
                                    />
                                </flux:table.cell>
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
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:checkbox.group>
            <flux:error name="selected" />

            <div class="flex justify-end gap-2">
                <flux:button wire:navigate :href="$backUrl">{{ __('common.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="file-text">{{ __('profile.export_as_pdf') }}</flux:button>
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('profile.no_memberships_found') }}" />
        @endif
    </form>
</div>
