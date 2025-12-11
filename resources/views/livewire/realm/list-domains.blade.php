<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="space-y-4">
            <flux:heading size="xl">{{ __('realms.domains_headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.domains_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="route('realms.domains.new', ['uid' => $uid])"
                :disabled="auth()->user()->cannot('create', \App\Ldap\Community::class)"
            >
                {{ __('New Domain') }}
            </flux:button>
        </div>
    </div>

    <flux:field>
        <flux:label>{{ __('committees.search') }}</flux:label>
        <flux:input wire:model.live.debounce="search" />
    </flux:field>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Short Name') }}</flux:table.column>
            <flux:table.column></flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($domainSlice->items() as $domain)
            <flux:table.row>
                <flux:table.cell>{{ $domain->getFirstAttribute('dc') }}</x-table.cell>
                <flux:table.cell>{{ $domain->getFirstAttribute('description') }}</x-table.cell>
                <flux:table.cell class="flex justify-end gap-2">
                    <flux:button
                        size="sm"
                        variant="danger"
                        icon="trash"
                        wire:click="deletePrepare('{{ $domain->getFirstAttribute('dc') }}')"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('domain.nothing_found') }}</span>
                    </div>
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('domain.delete_title', ['name' => $deleteDomain]) }}
            </x-slot:title>
            <x-slot:content>
                <div class="y">
                    <span>{{ __('domain.delete_warning', ['name' => $deleteDomain]) }}</span>
                </div>
            </x-slot:content>
            <x-slot:footer>
                <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                <flux:button variant="danger" type="submit">{{ __('Delete') }}</flux:button>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
