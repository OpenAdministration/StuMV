<div wire:init="loadCommittees">
    <div class="flex-col space-y-8 pb-6 sm:pb-8">
        <x-list-committees-header :community="$community" :realm="$realm_uid" />

        <flux:field>
            <flux:label>{{ __('committees.search') }}</flux:label>
            <flux:input icon="search" clearable wire:model.live.debounce.500ms="search" />
        </flux:field>

        <div wire:loading.flex wire:target="loadCommittees" class="flex justify-center py-16">
            <flux:icon.loading />
        </div>

        <div wire:loading.remove wire:target="loadCommittees">
            <ul>
                @forelse($nodes as $node)
                    @include('livewire.committee.committee-tree-node', [
                        'node' => $node,
                        'community' => $community,
                        'realm_uid' => $realm_uid,
                        'isLastItem' => $loop->last,
                        'isSearching' => $search !== '',
                    ])
                @empty
                    <flux:callout variant="warning" icon="info" heading="{{ __('committees.no_committees_found') }}" />
                @endforelse
            </ul>
        </div>
    </div>

    <flux:modal name="delete-committee" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="modal-header">{{ __('committees.delete_title', ['name' => $committeeToDeleteDescription]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('committees.delete_warning', ['name' => $committeeToDeleteDescription]) }}</flux:text>
                <flux:text class="mt-2">{{ __('committees.delete.confirm') }}<strong>{{ $committeeToDeleteOu }}</strong></flux:text>
                <flux:field class="mt-4">
                    <flux:input
                        wire:model="deleteConfirmText"
                        :placeholder="$committeeToDeleteOu"
                    />
                    <flux:error name="deleteConfirmText" />
                </flux:field>
            </div>
            <div class="flex flex-wrap justify-end gap-4">
                <flux:button
                    icon="ban"
                    x-on:click="$flux.modal('delete-committee').close()"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    icon="trash-2"
                    wire:click="deleteCommittee"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
