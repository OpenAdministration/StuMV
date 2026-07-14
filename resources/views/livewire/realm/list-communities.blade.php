<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.list_headline') }}</flux:heading>
            <flux:text class="text-base">{{  __('realms.list_explanation') }}</flux:text>
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
                icon="plus"
                wire:navigate
                :href="auth()->user()->can('create', \App\Ldap\Community::class) ? route('realms.new') : null"
                :disabled="auth()->user()->cannot('create', \App\Ldap\Community::class)"
            >
                {{ __('New Realm') }}
            </flux:button>
        </div>
    </div>

    <div
        class="flex items-center gap-3"
        x-data="{ showOnlyMine: $persist(false).as('realms.showOnlyMine') }"
        x-init="
            $wire.showOnlyMine = showOnlyMine;
            $watch('$wire.showOnlyMine', value => showOnlyMine = value);
        "
    >
        <flux:switch wire:model.live="showOnlyMine" label="{{ __('realms.show_only_mine') }}" align="left" />
    </div>

    <div class="flex justify-between">
        <flux:input.group wire:model.live.debounce="search" placeholder="{{ __('realms.search') }}"/>
    </div>
    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortField === 'description'" :direction="$sortDirection" wire:click="sortBy('description')">
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortField === 'ou'" :direction="$sortDirection" wire:click="sortBy('ou')">
                {{ __('realms.shortcode') }}
            </flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @php /** @var \App\Ldap\Community $realm */ @endphp
        @forelse($realms as $realm)
            <flux:table.row>
                <flux:table.cell>
                    <flux:link
                        :disabled="!($canEnter === true || Arr::has($canEnter, $realm->getShortCode()))"
                        wire:click="enter('{{ $realm->getShortCode() }}')"
                        class="cursor-pointer"
                    >
                        {{ $realm->getLongName() }}
                    </flux:link>
                </flux:table.cell>
                <flux:table.cell>{{ $realm->getShortCode() }}</flux:table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="log-in"
                        :disabled="!($canEnter === true || Arr::has($canEnter, $realm->getShortCode()))"
                        wire:click="enter('{{ $realm->getShortCode() }}')"
                    >
                        {{ __('Enter') }}
                    </flux:button>
                    <flux:dropdown>
                        <flux:button size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item
                                icon="pencil"
                                :disabled="Auth::user()->cannot('edit', $realm)"
                                :href="Auth::user()->can('edit', $realm) ? route('realms.edit', ['uid' => $realm->getShortCode()]) : null"
                                wire:navigate
                            >
                                {{ __('Edit') }}
                            </flux:menu.item>
                            <flux:menu.item
                                variant="danger"
                                icon="trash-2"
                                :disabled="Auth::user()->cannot('delete', $realm)"
                                wire:click="deletePrepare('{{ $realm->getShortCode() }}')">
                                {{ __('Delete') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_realms_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    @if(count($realms) > 0)
        <div class="pagination">
            <flux:pagination :paginator="$realms" />
        </div>
    @endif

    <form wire:submit="deleteCommit">
        <flux:modal name="delete" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('realms.delete_title', ['name' => $deleteRealmName]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('realms.delete_warning', ['name' => $deleteRealmName]) }}</flux:text>
                    <flux:text class="mt-2">{{ __('realms.delete.confirm') }}<strong>{{ $deleteRealmName }}</strong></flux:text>
                    <flux:field class="mt-4">
                        <flux:input
                            wire:model="deleteConfirmText"
                            :placeholder="$deleteRealmName"
                        />
                        <flux:error name="deleteConfirmText" />
                    </flux:field>
                </div>
                <div class="flex flex-wrap justify-end gap-4">
                    <flux:button icon="ban" wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" icon="trash-2" type="submit">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
