<div wire:init="loadCommittees">
    <div class="flex-col space-y-8 pb-6 sm:pb-8">
        <x-list-committees-header :community="$community" :realm="$realm_uid" />

        <flux:field>
            <flux:label>{{ __('committees.search') }}</flux:label>
            <flux:input icon="search" clearable wire:model.live.debounce.500ms="search" />
        </flux:field>

        <x-list-committees-navbar :realm="$realm_uid" :search="$search" />
        
        <div wire:loading.flex wire:target="loadCommittees" class="flex justify-center py-16">
            <flux:icon.loading />
        </div>
        
        <div wire:loading.remove wire:target="loadCommittees">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('committees.name') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($committees as $committee)
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:link
                                    wire:navigate
                                    :disabled="auth()->user()->cannot('view', [$committee])"
                                    href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                                >
                                    {{ $committee->getFirstAttribute('description') }}
                                </flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end items-center gap-2">
                                    <flux:dropdown>
                                        <flux:button size="sm" icon="ellipsis-vertical" title="{{ __('common.options') }}" />
                                        <flux:menu>
                                            <flux:menu.item
                                                icon="users"
                                                wire:navigate
                                                href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                                                class="md:hidden"
                                            >
                                                {{ __('committees.link_roles') }}
                                            </flux:menu.item>
                                            <flux:menu.item
                                                icon="pencil"
                                                href="{{ route('committees.edit', ['uid' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                                                :disabled="auth()->user()->cannot('edit', [$committee, $community])"
                                            >
                                                {{ __('committees.link_edit') }}
                                            </flux:menu.item>
                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash-2"
                                                x-on:click="$flux.modal('delete-committee-{{ $committee->getFirstAttribute('ou') }}').show()"
                                                :disabled="auth()->user()->cannot('edit', [$committee, $community])"
                                            >
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <tr>
                            <td colspan="3">
                                <flux:callout variant="warning" icon="info" heading="{{ __('committees.no_committees_found') }}" />
                            </td>
                        </tr>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</div>
