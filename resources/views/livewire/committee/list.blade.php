<div>
    <div class="flex-col space-y-8 pb-6 sm:pb-8">
        <div class="flex flex-col sm:flex-row gap-6">
            <div class="flex-1 space-y-4">
                <flux:heading size="xl">{{ __('committees.list.headline', ['name' => $community->getFirstAttribute('description')]) }}</flux:heading>
                <flux:text class="text-base">{{ __('committees.list.explain_text') }}</flux:text>
            </div>
            <div>
                <flux:button
                    variant="primary"
                    icon="plus"
                    wire:navigate
                    :href="route('committees.new', ['uid' => $realm_uid])" :disabled="auth()->user()->cannot('create', [\App\Ldap\Committee::class, $community])">
                    {{ __('New Committee') }}
                </flux:button>
            </div>
        </div>

        <flux:field>
            <flux:label>{{ __('committees.search') }}</flux:label>
            <flux:input wire:model.live.debounce.500ms="search" />
        </flux:field>

        <ul>
            @forelse($committees as $committee)
                <livewire:committee.committee-tree-item :dn="$committee->getDn()" :realm_uid="$realm_uid" :isLastItem="$loop->last" />
            @empty
                <div class="flex justify-center item-center">
                    <span class="text-gray-400 text-xl py-2 font-medium">{{ __('committees.no_committees_found') }}</span>
                </div>
            @endforelse
        </ul>
    </div>
</div>
