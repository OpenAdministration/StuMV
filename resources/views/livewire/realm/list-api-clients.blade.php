<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('api_clients.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('api_clients.explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.api-clients.new', ['uid' => $uid])"
            >
                {{ __('api_clients.new') }}
            </flux:button>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('api_clients.name') }}</flux:table.column>
            <flux:table.column>{{ __('api_clients.scopes') }}</flux:table.column>
            <flux:table.column>{{ __('api_clients.status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($clients as $client)
            <flux:table.row>
                <flux:table.cell>
                    <div class="font-medium">{{ $client->name }}</div>
                    <div class="text-xs text-zinc-500">{{ $client->id }}</div>
                </flux:table.cell>
                <flux:table.cell>
                    <div class="flex flex-wrap gap-1">
                        @foreach($client->scopes ?? [] as $scope)
                            <flux:badge size="sm">{{ $scope }}</flux:badge>
                        @endforeach
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    @if($client->revoked)
                        <flux:badge color="red" variant="solid">{{ __('api_clients.status_revoked') }}</flux:badge>
                    @else
                        <flux:badge color="green" variant="solid">{{ __('api_clients.status_active') }}</flux:badge>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    @unless($client->revoked)
                        <flux:button
                            size="sm"
                            variant="danger"
                            icon="ban"
                            wire:click="revokePrepare('{{ $client->id }}')"
                        >
                            {{ __('api_clients.revoke') }}
                        </flux:button>
                    @endunless
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('api_clients.no_clients_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="revokeCommit">
        <flux:modal name="revoke">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('api_clients.revoke_title', ['name' => $revokeClientName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('api_clients.revoke_warning', ['name' => $revokeClientName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('api_clients.revoke') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
