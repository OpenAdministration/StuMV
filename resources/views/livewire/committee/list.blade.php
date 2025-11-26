<div>
    <div class="flex-col space-y-8">
        <div class="flex flex-col sm:flex-row gap-6">
            <div class="space-y-4">
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

        <!--<flux:field>
            <flux:label>{{ __('committees.search') }}</flux:label>
            <flux:input wire:model.live.debounce="search" />
        </flux:field>-->

        <div>
            @forelse($committees as $committee)
                <livewire:committee.committee-tree-item :dn="$committee->getDn()" :realm_uid="$realm_uid" :isLastItem="$loop->last" />
            @empty
                <div class="flex justify-center item-center">
                    <span class="text-gray-400 text-xl py-2 font-medium">{{ __('committees.no_committees_found') }}</span>
                </div>
            @endforelse
        </div>
    </div>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('committees.delete_title', ['name' => $deleteCommitteeName]) }}
            </x-slot:title>
            <x-slot:content>
                <div class="y">
                    <span>{{ __('committees.delete_warning', ['name' => $deleteCommitteeName]) }}</span>
                    <span>{{ __('committees.delete.confirm') }}<strong>{{ $deleteCommitteeOu }}</strong></span>
                </div>
                <x-input.group wire:model="deleteConfirmText" :placeholder="$deleteCommitteeOu"/>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
