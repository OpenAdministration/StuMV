<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('sso_providers.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('sso_providers.explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.sso-providers.new', ['realm' => $uid])"
            >
                {{ __('sso_providers.new') }}
            </flux:button>
        </div>
    </div>

    <div class="pb-8">
        @if(count($providers) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('sso_providers.name') }}</flux:table.column>
                    <flux:table.column>{{ __('sso_providers.issuer') }}</flux:table.column>
                    <flux:table.column>{{ __('sso_providers.status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($providers as $provider)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $provider->name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs text-zinc-500">{{ $provider->issuer }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($provider->enabled)
                                <flux:badge color="green" variant="solid">{{ __('sso_providers.status_enabled') }}</flux:badge>
                            @else
                                <flux:badge color="red" variant="solid">{{ __('sso_providers.status_disabled') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                <flux:button
                                    size="sm"
                                    icon="pencil"
                                    wire:navigate
                                    href="{{ route('realms.sso-providers.edit', ['realm' => $uid, 'provider' => $provider->id]) }}"
                                >
                                    {{ __('common.edit') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash-2"
                                    wire:click="deletePrepare('{{ $provider->id }}')"
                                >
                                    {{ __('sso_providers.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('sso_providers.no_providers_found') }}" />
        @endif
    </div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('sso_providers.delete_title', ['name' => $deleteProviderName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('sso_providers.delete_warning', ['name' => $deleteProviderName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDelete()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('sso_providers.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
