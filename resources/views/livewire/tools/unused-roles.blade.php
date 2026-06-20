<div class="max-w-6xl mx-auto w-full">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.unusedRoles_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.unusedRoles_explanation') }}</flux:text>
    </div>
    <div wire:loading.class="hidden">
        <flux:tab.group>
            <flux:tabs wire:model="tab">
                <flux:tab name="roles">{{ __('tools.roles') }}</flux:tab>
                <flux:tab name="committees">{{ __('tools.committees') }}</flux:tab>
            </flux:tabs>
            <flux:tab.panel name="roles">
                <div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('tools.role') }}</flux:table.column>
                            <flux:table.column>{{ __('tools.committee') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($unusedRoles as $role)
                                <flux:table.row>
                                    <flux:table.cell>
                                        {{ $role->getFirstAttribute('description') }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:link
                                            wire:navigate
                                            href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $role->committee()->getFirstAttribute('ou')]) }}"
                                        >
                                            {{ $role->committee()->getFirstAttribute('description') }}
                                        </flux:link>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:tab.panel>
            <flux:tab.panel name="committees">
                <div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('tools.committee') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach($unusedCommittees as $committee)
                                <flux:table.row>
                                    <flux:table.cell>
                                        <flux:link
                                            wire:navigate
                                            href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                                        >
                                            {{ $committee->getFirstAttribute('description') }}
                                        </flux:link>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:tab.panel>
        </flux:tab.group>
    </div>
    <div wire:loading.delay class="space-y-3">
        <flux:skeleton class="h-10 w-full" />
        <flux:skeleton class="h-10 w-full" />
        <flux:skeleton class="h-10 w-full" />
        <flux:skeleton class="h-10 w-full" />
        <flux:skeleton class="h-10 w-full" />
    </div>
</div>
