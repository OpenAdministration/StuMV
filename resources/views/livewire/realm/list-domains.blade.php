<div class="flex-col">
    <div class="flex flex-col sm:flex-row gap-6 mb-8">
        <div class="flex-1 space-y-4">
            <flux:heading size="xl">{{ __('realms.domains_headline') }}</flux:heading>
            <flux:text class="text-base">{{ __('realms.domains_explanation') }}</flux:text>
        </div>
        <div>
            <flux:button
                variant="primary"
                icon="plus"
                wire:navigate
                :href="auth()->user()->can('create', \App\Ldap\Community::class) ? route('realms.domains.new', ['realm' => $uid]) : null"
                :disabled="auth()->user()->cannot('create', \App\Ldap\Community::class)"
            >
                {{ __('domain.new_button') }}
            </flux:button>
        </div>
    </div>

    <flux:field class="mb-8">
        <flux:label>{{ __('committees.search') }}</flux:label>
        <flux:input wire:model.live.debounce="search" />
    </flux:field>

    <div class="pb-8">
        @if(count($domains) > 0)
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortField === 'dc'" :direction="$sortDirection" wire:click="sortBy('dc')">{{ __('common.short_name') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                @foreach($domains as $domain)
                    <flux:table.row>
                        <flux:table.cell>{{ $domain->getFirstAttribute('dc') }}</flux:table.cell>
                        <flux:table.cell>{{ $domain->getFirstAttribute('description') }}</flux:table.cell>
                        <flux:table.cell class="flex justify-end gap-2">
                            <flux:button
                                size="sm"
                                variant="danger"
                                icon="trash-2"
                                wire:click="deletePrepare('{{ $domain->getFirstAttribute('dc') }}')"
                            >
                                {{ __('common.delete') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="pagination">
                <flux:pagination :paginator="$domains" />
            </div>
        @else
            <flux:callout variant="warning" icon="circle-alert" heading="{{ __('domain.nothing_found') }}" />
        @endif
    </div>

    <form wire:submit="deleteCommit">
        <flux:modal name="delete">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="modal-header">{{ __('domain.delete_title', ['name' => $deleteDomain]) }}</flux:heading>
                    <flux:text class="mt-2">{{ __('domain.delete_warning', ['name' => $deleteDomain]) }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button wire:click="close()">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('common.delete') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </form>
</div>
