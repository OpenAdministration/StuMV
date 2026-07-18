<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('oidc_clients.headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('oidc_clients.explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('oidc-clients.new')"
            >
                {{ __('oidc_clients.new') }}
            </flux:button>
        </div>
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('oidc_clients.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div class="pb-8">
        @if(count($clients) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'name'" :direction="$sortDirection" wire:click="sortBy('name')">{{ __('oidc_clients.name') }}</flux:table.column>
                    <flux:table.column>{{ __('oidc_clients.scopes') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'revoked'" :direction="$sortDirection" wire:click="sortBy('revoked')">{{ __('oidc_clients.status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($clients as $client)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="font-medium">{{ $client->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $client->id }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-2">
                                @foreach($client->scopes ?? [] as $scope)
                                    <flux:badge>{{ $scope }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($client->revoked)
                                <flux:badge color="red" variant="solid">{{ __('oidc_clients.status_revoked') }}</flux:badge>
                            @else
                                <flux:badge color="green" variant="solid">{{ __('oidc_clients.status_active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                <flux:dropdown>
                                    <flux:button size="sm" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @unless($client->revoked)
                                            <flux:menu.item
                                                icon="pencil"
                                                wire:navigate
                                                href="{{ route('oidc-clients.edit', ['client' => $client->id]) }}"
                                            >
                                                {{ __('common.edit') }}
                                            </flux:menu.item>
                                            <flux:menu.item
                                                variant="danger"
                                                icon="ban"
                                                wire:click="revokePrepare('{{ $client->id }}')"
                                            >
                                                {{ __('oidc_clients.revoke') }}
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash-2"
                                                wire:click="deletePrepare('{{ $client->id }}')"
                                            >
                                                {{ __('oidc_clients.delete') }}
                                            </flux:menu.item>
                                        @endunless
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pagination">
                <flux:pagination :paginator="$clients" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('oidc_clients.no_clients_found') }}" />
        @endif
    </div>

    <form wire:submit="revokeCommit">
        <flux:modal name="revoke">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('oidc_clients.revoke_title', ['name' => $revokeClientName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('oidc_clients.revoke_warning', ['name' => $revokeClientName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('oidc_clients.revoke') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('oidc_clients.delete_title', ['name' => $deleteClientName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('oidc_clients.delete_warning', ['name' => $deleteClientName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDelete()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('oidc_clients.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
