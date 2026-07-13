<div class="flex-col space-y-8">
    <div class="flex flex-col sm:flex-row gap-6">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.domains_headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.domains_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="auth()->user()->can('create', \App\Ldap\Community::class) ? route('realms.domains.new', ['uid' => $uid]) : null"
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
            <flux:table.column sortable :sorted="$sortField === 'dc'" :direction="$sortDirection" wire:click="sortBy('dc')">{{ __('Short Name') }}</flux:table.column>
            <flux:table.column></flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
        @forelse($domains as $domain)
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

    @if(count($domains) > 0)
        <div class="pagination">
            <flux:pagination :paginator="$domains" />
        </div>
    @endif

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('domain.delete_title', ['name' => $deleteDomain]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('domain.delete_warning', ['name' => $deleteDomain]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
