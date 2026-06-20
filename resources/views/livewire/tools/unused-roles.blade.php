<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.unusedRoles_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.unusedRoles_explanation') }}</flux:text>
    </div>
    <div>
        <flux:tab.group>
            <flux:tabs wire:model="tab">
                <flux:tab name="roles">{{ __('tools.roles') }}</flux:tab>
                <flux:tab name="committees">{{ __('tools.committees') }}</flux:tab>
            </flux:tabs>
            <flux:tab.panel name="roles">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('tools.role') }}</flux:table.column>
                        <flux:table.column>{{ __('tools.committee') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($unusedRoles as $unusedRole)
                            <flux:table.row>
                                <flux:table.cell>
                                    {{ $unusedRole->getFirstAttribute('description') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $unusedRole->committee()->getFirstAttribute('description') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>
            <flux:tab.panel name="committees">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('tools.committee') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($unusedCommittees as $unusedCommittee)
                            <flux:table.row>
                                <flux:table.cell>
                                    {{ $unusedCommittee()->getFirstAttribute('description') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>
        </flux:tab.group>
    </div>
</div>