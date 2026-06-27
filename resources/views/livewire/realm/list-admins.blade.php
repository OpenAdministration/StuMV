<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.admins_headline', ['name' => $community->getFirstAttribute('description'), 'uid' => $community_name]) }}</flux:heading>
            <flux:text class="text-base">{{  __('realms.admins_explanation') }}</flux:text>
            <flux:button
                size="sm"
                variant="primary"
                icon="mail"
                href="mailto:{{ config('app.help_contact_mail') }}"
            >
                {{ __('Contact us') }}
            </flux:button>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="user-plus"
                wire:navigate
                :href="route('realms.admins.new', ['uid' => $community_name])"
                :disabled="auth()->user()->cannot('add_admin', $community)"
            >
                {{ __('Add Admin') }}
            </flux:button>
        </div>
    </div>

    <!--<flux:field>
        <flux:label>{{ __('realms.search_admins') }}</flux:label>
        <flux:input type="text" icon="magnifying-glass" wire:model.live.debounce="search" />
    </flux:field>-->

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Username') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($realm_admins as $realm_admin)
            <flux:table.row>
                <flux:table.cell>
                    @can('admin', $community)
                        <flux:link
                            wire:navigate
                            :disabled="auth()->user()->cannot('admin', [$community])"
                            href="{{ route('profile', ['username' => $realm_admin->uid[0]]) }}"
                        >
                            {{ $realm_admin->cn[0] }}
                        </flux:link>
                    @else
                        {{ $realm_admin->cn[0] }}
                    @endcan
                </flux:table.cell>
                <flux:table.cell>{{ $realm_admin->uid[0] }}</flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                variant="danger"
                                icon="user-minus"
                                :disabled="auth()->user()->cannot('remove_admin', $community)"
                                wire:click="deletePrepare('{{ $realm_admin->uid[0] }}')"
                            >
                                {{ __('Delete') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_admins_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.delete_admin_title', ['name' => $deleteAdminName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.delete_admin_warning', ['name' => $deleteAdminName]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
