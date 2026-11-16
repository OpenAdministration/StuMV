<div class="max-w-7xl mx-auto w-full flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.list_headline') }}</flux:heading>
            <flux:text class="text-base">{{  __('realms.list_explanation', ['operator' => config('app.operator_name')]) }}</flux:text>
            <flux:button
                size="sm"
                variant="primary"
                icon="mail"
                href="mailto:{{ config('app.help_contact_mail') }}"
            >
                {{ __('common.contact_us') }}
            </flux:button>
        </div>
        <div>
            @can('create', \App\Ldap\Community::class)
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:navigate
                    :href="route('realms.new')"
                >
                    {{ __('realms.new_button') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div
        class="flex items-center gap-3 mb-8"
        x-data="persistedToggle('showOnlyMine', 'realms.showOnlyMine', false)"
    >
        <flux:switch wire:model.live="showOnlyMine" label="{{ __('realms.show_only_mine') }}" align="left" />
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('realms.search') }}</flux:label>
        <flux:input icon="search" clearable wire:model.live="search" />
    </flux:field>

    <div>
        @if(count($realms) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'description'" :direction="$sortDirection" wire:click="sortBy('description')">{{ __('common.name') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortField === 'ou'" :direction="$sortDirection" wire:click="sortBy('ou')">{{ __('realms.shortcode') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @php /** @var \App\Ldap\Community $realm */ @endphp
                @foreach($realms as $realm)
                    <flux:table.row>
                        <flux:table.cell>
                            @if($canEnter === true || Arr::has($canEnter, $realm->getShortCode()))
                                <flux:link
                                    wire:click="enter('{{ $realm->getShortCode() }}')"
                                    class="cursor-pointer"
                                >
                                    {{ $realm->getLongName() }}
                                </flux:link>
                            @else
                                {{ $realm->getLongName() }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $realm->getShortCode() }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end items-center gap-2">
                                @if($canEnter === true || Arr::has($canEnter, $realm->getShortCode()))
                                    <flux:button
                                        size="sm"
                                        variant="primary"
                                        icon="log-in"
                                        wire:click="enter('{{ $realm->getShortCode() }}')"
                                    >
                                        {{ __('realms.enter_button') }}
                                    </flux:button>
                                @endif
                                @canany(['edit', 'delete'], $realm)
                                    <flux:dropdown>
                                        <flux:button size="sm" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            @can('edit', $realm)
                                                <flux:menu.item
                                                    icon="pencil"
                                                    :href="route('realms.edit', ['realm' => $realm->getShortCode()])"
                                                    wire:navigate
                                                >
                                                    {{ __('common.edit') }}
                                                </flux:menu.item>
                                            @endcan
                                            @can('delete', $realm)
                                                <flux:menu.item
                                                    variant="danger"
                                                    icon="trash-2"
                                                    wire:click="deletePrepare('{{ $realm->getShortCode() }}')">
                                                    {{ __('common.delete') }}
                                                </flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcanany
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="pagination">
                <flux:pagination :paginator="$realms" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('realms.no_realms_found') }}" />
        @endif
    </div>

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
                    <flux:button icon="ban" wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" icon="trash-2" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
