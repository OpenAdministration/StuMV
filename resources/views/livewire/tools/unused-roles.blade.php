<div class="max-w-6xl mx-auto w-full" wire:init="loadUnusedRoles">
    <div class="space-y-4 mb-8">
        <flux:heading size="xl">{{ __('tools.unused_roles_headline') }}</flux:heading>
        <flux:text class="text-base">{{  __('tools.unused_roles_explanation') }}</flux:text>
    </div>
    <div wire:loading.flex wire:target="loadUnusedRoles" class="flex justify-center py-16">
        <flux:icon.loading />
    </div>

    <div wire:loading.remove wire:target="loadUnusedRoles" class="pb-8">
        <flux:tab.group>
            <flux:tabs>
                <flux:tab name="roles">{{ __('tools.roles') }}</flux:tab>
                <flux:tab name="committees">{{ __('tools.committees') }}</flux:tab>
            </flux:tabs>
            <flux:tab.panel name="roles" class="pt-4">
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
                                        href="{{ route('committees.roles', ['realm' => $realm_uid, 'ou' => $role->committee()->getFirstAttribute('ou')]) }}"
                                    >
                                        {{ $role->committee()->getFirstAttribute('description') }}
                                    </flux:link>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>
            <flux:tab.panel name="committees" class="pt-4">
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
                                        href="{{ route('committees.roles', ['realm' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                                    >
                                        {{ $committee->getFirstAttribute('description') }}
                                    </flux:link>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:tab.panel>
        </flux:tab.group>
    </div>
</div>

