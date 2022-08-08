<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('realms:index') }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="Durchsuche Realms"></x-input>
        <x-button.primary class="flex"><x-fas-plus class="text-white align-middle"/>&nbsp;Neu</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('uid')" :direction="$sortField === 'uid' ? $sortDirection : null">
                Kürzel
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('long_name')" :direction="$sortField === 'long_name' ? $sortDirection : null"
                class="w-full"
            >
                Name
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($realms as $realm)
            <x-table.row>
                <x-table.cell>{{ $realm->uid }}</x-table.cell>
                <x-table.cell>{{ $realm->long_name }}</x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $realm->uid }}')">Edit</x-button.link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="3">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">Keine Realms gefunden</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $realms->links() }}

    <form wire:submit.prevent="save">
        <x-modal.dialog wire:model.defer="showEditModal">
            <x-slot:title>
                Realm
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="editRealm.uid">
                    <x-slot:label>Kürzel</x-slot:label>
                </x-input.group>
                <x-input.group wire:model="editRealm.long_name">
                    <x-slot:label>Voller Name</x-slot:label>
                </x-input.group>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">Abbrechen</x-button.secondary>
                <x-button.primary type="submit">Speichern</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>
</div>
