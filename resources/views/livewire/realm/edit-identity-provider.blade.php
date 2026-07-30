<div>
    <div class="mb-8">
        <flux:heading size="xl" class="mb-4">{{ __('identity_providers.edit_title') }}</flux:heading>
        <flux:text class="text-base">{{ __('identity_providers.explanation') }}</flux:text>
    </div>

    <div class="pb-8">
        <flux:tab.group>
            <flux:tabs>
                <flux:tab name="general">{{ __('identity_providers.tab_general') }}</flux:tab>
                <flux:tab name="role-mappings">{{ __('identity_providers.tab_mappings') }}</flux:tab>
                <flux:tab name="group-mappings">{{ __('identity_providers.tab_group_mappings') }}</flux:tab>
            </flux:tabs>

            <flux:tab.panel name="general" class="pt-8">
                <x-livewire-form class="space-y-8">
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
                        <flux:label>{{ __('identity_providers.backchannel_logout_url') }}</flux:label>
                        <flux:description>{{ __('identity_providers.backchannel_logout_url_description') }}</flux:description>
                        <flux:input readonly copyable value="{{ route('identity-provider.backchannel-logout', ['realm' => $uid, 'provider' => $providerId]) }}" />
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
            </flux:tab.panel>

            <flux:tab.panel name="role-mappings" class="pt-8 space-y-8">
                <div class="space-y-4">
                    <div>
                        <p>{{ __('identity_providers.mappings_explanation') }}</p>
                    </div>

                    @if(count($mappingRows) > 0)
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('identity_providers.mappings_external_group') }}</flux:table.column>
                                <flux:table.column>{{ __('identity_providers.mappings_committee') }}</flux:table.column>
                                <flux:table.column>{{ __('identity_providers.mappings_role') }}</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                            @foreach($mappingRows as $row)
                                @php($mapping = $row['mapping'])
                                @php($committee = $row['committee'])
                                @php($role = $row['role'])
                                <flux:table.row>
                                    <flux:table.cell>{{ $mapping->external_group }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($committee)
                                            <flux:link
                                                wire:navigate
                                                href="{{ route('committees.roles', ['realm' => $uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                                            >
                                                {{ $committee->getFirstAttribute('description') }}
                                            </flux:link>
                                        @else
                                            <div class="text-xs text-zinc-500">{{ $mapping->committee_dn }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($committee && $role)
                                            <flux:link
                                                wire:navigate
                                                href="{{ route('committees.roles.members', ['realm' => $uid, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')]) }}"
                                            >
                                                {{ $role->getFirstAttribute('description') }}
                                            </flux:link>
                                        @else
                                            <div class="text-xs text-zinc-500">{{ $mapping->role_cn }}</div>
                                        @endif
                                    </flux:table.cell>
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
            </flux:tab.panel>

            <flux:tab.panel name="group-mappings" class="pt-8 space-y-8">
                <div class="space-y-4">
                    <div>
                        <p>{{ __('identity_providers.group_mappings_explanation') }}</p>
                    </div>

                    @if(count($groupMappingRows) > 0)
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>{{ __('identity_providers.mappings_external_group') }}</flux:table.column>
                                <flux:table.column>{{ __('identity_providers.group_mappings_group') }}</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                            @foreach($groupMappingRows as $row)
                                @php($mapping = $row['mapping'])
                                @php($group = $row['group'])
                                <flux:table.row>
                                    <flux:table.cell>{{ $mapping->external_group }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($group)
                                            <flux:link
                                                wire:navigate
                                                href="{{ route('realms.groups.members', ['realm' => $uid, 'cn' => $group->getFirstAttribute('cn')]) }}"
                                            >
                                                {{ $group->getFirstAttribute('description') }}
                                            </flux:link>
                                        @else
                                            <div class="text-xs text-zinc-500">{{ $mapping->group_dn }}</div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end">
                                            <flux:button
                                                size="sm"
                                                variant="danger"
                                                icon="trash-2"
                                                wire:click="deleteGroupMappingPrepare('{{ $mapping->id }}')"
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
                        <flux:callout variant="warning" icon="circle-alert" heading="{{ __('identity_providers.no_group_mappings_found') }}" />
                    @endif

                    <form wire:submit="addGroupMapping" class="grid sm:grid-cols-2 gap-4 items-start">
                        <flux:field>
                            <flux:label>{{ __('identity_providers.mappings_external_group') }}</flux:label>
                            <flux:input wire:model="new_group_external_group" placeholder="{{ __('identity_providers.mappings_external_group_placeholder') }}" />
                            <flux:error name="new_group_external_group" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('identity_providers.group_mappings_group') }}</flux:label>
                            <flux:select variant="listbox" searchable wire:model="new_group_dn">
                                @foreach($groups as $group)
                                    <flux:select.option value="{{ $group->getDn() }}">{{ $group->getFirstAttribute('description') }} ({{ $group->getFirstAttribute('cn') }})</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="new_group_dn" />
                        </flux:field>

                        <div class="sm:col-span-2 flex justify-end">
                            <flux:button type="submit" icon="plus">{{ __('identity_providers.mappings_add') }}</flux:button>
                        </div>
                    </form>
                </div>
            </flux:tab.panel>
        </flux:tab.group>
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

    <form wire:submit="deleteGroupMappingCommit">
        <flux:modal name="delete-group-mapping">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('identity_providers.mapping_delete_title', ['name' => $deleteGroupMappingLabel]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('identity_providers.mapping_delete_warning', ['name' => $deleteGroupMappingLabel]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-4">
                    <flux:button wire:click="closeDeleteGroupMapping()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="danger" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
