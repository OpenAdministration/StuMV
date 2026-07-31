<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('identity_providers.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('identity_providers.explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.identity-providers.new', ['realm' => $uid])"
            >
                {{ __('identity_providers.new') }}
            </flux:button>
        </div>
    </div>

    <div class="pb-8">
        @if(count($providers) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('identity_providers.name') }}</flux:table.column>
                    <flux:table.column>{{ __('identity_providers.status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($providers as $provider)
                    <flux:table.row>
                        <flux:table.cell>
                            <flux:link
                                wire:navigate
                                href="{{ route('realms.identity-providers.edit', ['realm' => $uid, 'provider' => $provider->id]) }}"
                            >
                                {{ $provider->name }}
                            </flux:link>
                            <div class="text-xs text-zinc-500">{{ $provider->id }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($provider->enabled)
                                <flux:badge color="green" variant="solid">{{ __('identity_providers.status_enabled') }}</flux:badge>
                            @else
                                <flux:badge color="red" variant="solid">{{ __('identity_providers.status_disabled') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                <flux:dropdown>
                                    <flux:button size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item
                                            icon="pencil"
                                            wire:navigate
                                            href="{{ route('realms.identity-providers.edit', ['realm' => $uid, 'provider' => $provider->id]) }}"
                                        >
                                            {{ __('common.edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item
                                            variant="danger"
                                            icon="trash-2"
                                            wire:click="deletePrepare('{{ $provider->id }}')"
                                        >
                                            {{ __('identity_providers.delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('identity_providers.no_providers_found') }}" />
        @endif
    </div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('identity_providers.delete_title', ['name' => $deleteProviderName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('identity_providers.delete_warning', ['name' => $deleteProviderName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDelete()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('identity_providers.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
