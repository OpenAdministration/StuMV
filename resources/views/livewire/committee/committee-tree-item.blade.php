<div>
    <div class="grid grid-cols-[3rem_1fr_auto]">
        @if($hasChildren)
            <div class="flex justify-start items-center">
                @if($unfolded)
                    <flux:button
                        size="sm"
                        icon="chevron-down"
                        wire:click="$set('unfolded', false)"
                        class="cursor-pointer"
                    />
                @else
                    <flux:button
                        size="sm"
                        icon="chevron-right"
                        wire:click="getChildren('{{ $committee->getDn() }}')"
                        class="cursor-pointer"
                    />
                @endif
            </div>
        @else
            @if($isLastItem)
                <div class="flex justify-center items-end px-4 pb-6">
                    <flux:separator vertical class="w-[2px]!" />
                    <flux:separator class="h-[2px]!" />
                </div>
            @else
                <div class="flex justify-center items-center px-4">
                    <flux:separator vertical class="w-[2px]!" />
                    <flux:separator class="h-[2px]!" />
                </div>
            @endif
        @endif

        <div class="flex items-center border-b border-zinc-200 dark:border-zinc-700 py-2">
            <flux:link
                wire:navigate
                href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
            >
                {{ $committee->getFirstAttribute('description') }}
            </flux:link>
        </div>
        <div class="flex justify-end gap-2 border-b border-zinc-200 dark:border-zinc-700 py-2">
            <flux:button
                size="sm"
                variant="primary"
                icon="users"
                wire:navigate
                href="{{ route('committees.roles', ['uid' => $realm_uid, 'ou' => $committee->getFirstAttribute('ou')]) }}"
                class="hidden! md:flex!"
            >
                {{ __('committees.link_roles') }}
            </flux:button>

            <flux:dropdown>
                <flux:button size="sm" icon="ellipsis-vertical" />
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
    </div>

    @if(count($children) > 0 && $unfolded)
        @foreach($children as $child)
            <div class="grid grid-cols-[3rem_1fr]">
                <div class="flex pl-4 items-center">
                    @if(!$isLastItem)
                        <flux:separator vertical class="w-[2px]!" />
                    @endif
                </div>
                <livewire:committee.committee-tree-item :dn="$child->getDn()" :realm_uid="$realm_uid" :isLastItem="$loop->last" />
            </div>
        @endforeach
    @endif

    <flux:modal name="delete-committee-{{ $committee->getFirstAttribute('ou') }}" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('committees.delete_title', ['name' => $committee->getFirstAttribute('description')]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('committees.delete_warning', ['name' => $committee->getFirstAttribute('description')]) }}</flux:text>
                <flux:text class="mt-2">{{ __('committees.delete.confirm') }}<strong>{{ $committee->getFirstAttribute('ou') }}</strong></flux:text>
                <flux:field class="mt-4">
                    <flux:input
                        wire:model="deleteConfirmText"
                        :placeholder="$committee->getFirstAttribute('ou')"
                    />
                    <flux:error name="deleteConfirmText" />
                </flux:field>
            </div>
            <div class="flex">
                <flux:spacer />
                <flux:button
                    icon="ban"
                    x-on:click="$flux.modal('delete-committee-{{ $committee->getFirstAttribute('ou') }}').close()"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    icon="trash-2"
                    wire:click="deleteCommittee('{{ $committee->getDn() }}', '{{ $committee->getFirstAttribute('ou') }}')"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>