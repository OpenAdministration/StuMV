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
            <ul>
                @forelse($committees as $committee)
                    <livewire:committee.committee-tree-item
                        :dn="$committee->getDn()"
                        :realm_uid="$realm_uid"
                        :isLastItem="$loop->last"
                        :search="$search"
                        wire:key="committee-tree-root-{{ $committee->getDn() }}-{{ $search }}"
                    />
                @empty
                    <flux:callout variant="warning" icon="info" heading="{{ __('committees.no_committees_found') }}" />
                @endforelse
            </ul>
        </div>
    </div>
</div>
