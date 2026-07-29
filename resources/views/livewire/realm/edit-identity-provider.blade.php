<div class="space-y-8">
    <x-livewire-form class="space-y-8">
        <div>
            <flux:heading size="xl" class="mb-4">{{ __('identity_providers.edit_title') }}</flux:heading>
            <flux:text class="text-base">{{ __('identity_providers.explanation') }}</flux:text>
        </div>

        <flux:field>
            <flux:label>{{ __('identity_providers.name') }}</flux:label>
            <flux:input wire:model="name" placeholder="{{ __('identity_providers.name_placeholder') }}" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.issuer') }}</flux:label>
            <flux:description>{{ __('identity_providers.issuer_description') }}</flux:description>
            <flux:input wire:model="issuer" placeholder="https://idp.example.com" />
            <flux:error name="issuer" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.client_id') }}</flux:label>
            <flux:input wire:model="client_id" />
            <flux:error name="client_id" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.client_secret') }}</flux:label>
            <flux:description>{{ __('identity_providers.client_secret_edit_description') }}</flux:description>
            <flux:input type="password" wire:model="client_secret" placeholder="••••••••" />
            <flux:error name="client_secret" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('identity_providers.groups_claim') }}</flux:label>
            <flux:description>{{ __('identity_providers.groups_claim_description') }}</flux:description>
            <flux:input wire:model="groups_claim" />
            <flux:error name="groups_claim" />
        </flux:field>

        <flux:switch wire:model="enabled" label="{{ __('identity_providers.enabled') }}" />

        <x-slot:abort_route>
            {{ route('realms.identity-providers', ['realm' => $uid]) }}
        </x-slot:abort_route>
    </x-livewire-form>

    <flux:separator />

    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('identity_providers.mappings_headline') }}</flux:heading>
            <flux:text class="mt-2">{{ __('identity_providers.mappings_explanation') }}</flux:text>
        </div>

        @if(count($mappings) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('identity_providers.mappings_external_group') }}</flux:table.column>
                    <flux:table.column>{{ __('identity_providers.mappings_committee') }}</flux:table.column>
                    <flux:table.column>{{ __('identity_providers.mappings_role') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($mappings as $mapping)
                    <flux:table.row>
                        <flux:table.cell>{{ $mapping->external_group }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="text-xs text-zinc-500">{{ $mapping->committee_dn }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $mapping->role_cn }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end">
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    icon="trash-2"
                                    wire:click="deleteMappingPrepare('{{ $mapping->id }}')"
                                >
                                    {{ __('common.delete') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('identity_providers.no_mappings_found') }}" />
        @endif

        <form wire:submit="addMapping" class="grid sm:grid-cols-3 gap-4 items-start">
            <flux:field>
                <flux:label>{{ __('identity_providers.mappings_external_group') }}</flux:label>
                <flux:input wire:model="new_external_group" placeholder="{{ __('identity_providers.mappings_external_group_placeholder') }}" />
                <flux:error name="new_external_group" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('identity_providers.mappings_committee') }}</flux:label>
                <flux:select variant="listbox" searchable wire:model.live="new_committee_dn">
                    @foreach($committees as $committee)
                        <flux:select.option value="{{ $committee->getDn() }}">{{ $committee->getFirstAttribute('description') }} ({{ $committee->getFirstAttribute('ou') }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="new_committee_dn" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('identity_providers.mappings_role') }}</flux:label>
                <flux:select variant="listbox" searchable wire:model="new_role_cn" :disabled="empty($new_committee_dn)">
                    @foreach($roles as $role)
                        <flux:select.option value="{{ $role->getFirstAttribute('cn') }}">{{ $role->getFirstAttribute('description') }} ({{ $role->getFirstAttribute('cn') }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="new_role_cn" />
            </flux:field>

            <div class="sm:col-span-3 flex justify-end">
                <flux:button type="submit" icon="plus">{{ __('identity_providers.mappings_add') }}</flux:button>
            </div>
        </form>
    </div>

    <form wire:submit="deleteMappingCommit">
        <flux:modal name="delete-mapping">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('identity_providers.mapping_delete_title', ['name' => $deleteMappingLabel]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('identity_providers.mapping_delete_warning', ['name' => $deleteMappingLabel]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDeleteMapping()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
