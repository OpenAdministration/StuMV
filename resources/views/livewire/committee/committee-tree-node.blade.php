@php($committee = $node['committee'])
<li>
    <div class="grid grid-cols-[3rem_1fr_auto]">
        @if($node['hasChildren'])
            <div class="flex justify-start items-center">
                @if($node['unfolded'])
                    <flux:button
                        size="sm"
                        icon="chevron-down"
                        wire:click="toggleChildren('{{ $committee->getDn() }}')"
                        class="cursor-pointer"
                        title="{{ __('committees.foldSubItems', ['committee' => $committee->getFirstAttribute('description')]) }}"
                    />
                @else
                    <flux:button
                        size="sm"
                        icon="chevron-right"
                        wire:click="toggleChildren('{{ $committee->getDn() }}')"
                        class="cursor-pointer"
                        title="{{ __('committees.unfoldSubItems', ['committee' => $committee->getFirstAttribute('description')]) }}"
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
        <div class="flex justify-end items-center gap-2 border-b border-zinc-200 dark:border-zinc-700 py-2 pl-4">
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
                        wire:click="confirmDeleteCommittee('{{ $committee->getDn() }}')"
                        :disabled="auth()->user()->cannot('edit', [$committee, $community])"
                    >
                        {{ __('Delete') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if(count($node['children']) > 0 && $node['unfolded'])
        <ul>
            @foreach($node['children'] as $child)
                <div class="grid grid-cols-[3rem_1fr]">
                    <div class="flex pl-4 items-center">
                        @if(!$isLastItem)
                            <flux:separator vertical class="w-[2px]!" />
                        @endif
                    </div>
                    @include('livewire.committee.committee-tree-node', [
                        'node' => $child,
                        'community' => $community,
                        'realm_uid' => $realm_uid,
                        'isLastItem' => $loop->last,
                    ])
                </div>
            @endforeach
        </ul>
    @endif
</li>
