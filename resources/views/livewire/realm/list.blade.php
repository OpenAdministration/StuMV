<div class="flex-col space-y-4">
    <div class="flex justify-between">
        <x-input.group wire:model.live.debounce="search" placeholder="{{ __('realms.search') }}"/>
        <x-button.link-primary href="{{ route('realms.new') }}">Neuer Realm</x-button.link-primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('uid')"
                             :direction="$sortField === 'uid' ? $sortDirection : null">
                {{ __('realms.shortcode') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('long_name')"
                :direction="$sortField === 'long_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @php /** @var \App\Ldap\Community $realm */ @endphp
        @forelse($realmSlice->items() as $realm)
            <x-table.row>
                <x-table.cell>{{ $realm->getShortCode() }}</x-table.cell>
                <x-table.cell>{{ $realm->getLongName() }}</x-table.cell>
                <x-table.cell>
                    <x-link href="#" wire:click="register('{{ $realm->getShortCode() }}')">
                        <x-fas-dungeon class="inline w-10 h-10"/> {{ __('Enter') }}
                    </x-link>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger
                        wire:click="deletePrepare('{{ $realm->getShortCode() }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
                <x-table.cell>
                    <x-link
                        href="{{ route('realms.edit', ['uid' => $realm->getShortCode()]) }}">{{ __('Edit') }}</x-link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="6">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_realms_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>

    <form wire:submit="deleteCommit">
        <x-modal.confirmation wire:model="showDeleteModal">
            <x-slot:title>
                {{ __('realms.delete_title', ['name' => $deleteRealmName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_warning', ['name' => $deleteRealmName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
