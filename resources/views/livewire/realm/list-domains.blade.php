<div class="flex-col space-y-4">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">Lorem Ipsum</h1>
            <p class="mt-2 text-sm text-gray-700">Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren,</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
            <x-button.link-primary :href="route('realms.domains.new', ['uid' => $uid])" icon-leading="fas-plus" :disabled="auth()->user()->cannot('create', \App\Ldap\Community::class)">
                {{ __('New Domain') }}
            </x-button.link-primary>
        </div>
    </div>
    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('committees.search') }}"></x-input.group>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('ou')" :direction="$sortField === 'name' ? $sortDirection : null"
            >
                {{ __('Short Name') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($domainSlice->items() as $domain)
            <x-table.row>
                <x-table.cell>{{ $domain->getFirstAttribute('dc') }}</x-table.cell>
                <x-table.cell>{{ $domain->getFirstAttribute('description') }}</x-table.cell>
                <x-table.cell>

                </x-table.cell>
                <x-table.cell>

                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $domain->getFirstAttribute('dc') }}')">
                        <x-fas-trash/> {{ __('Delete') }}
                    </x-button.link-danger>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('domains.nothing_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('domains.delete_title', ['name' => $deleteDomain]) }}
            </x-slot:title>
            <x-slot:content>
                <div class="y">
                    <span>{{ __('domains.delete_warning', ['name' => $deleteDomain]) }}</span>
                </div>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
