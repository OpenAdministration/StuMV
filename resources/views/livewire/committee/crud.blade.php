<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('committees:index') }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="{{ __('committees.search') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading sortable wire:click="sortBy('realm_uid')" :direction="$sortField === 'realm_uid' ? $sortDirection : null">
                Realm {{ __('realms.shortcode') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('name')" :direction="$sortField === 'name' ? $sortDirection : null"
            >
                {{ __('committees.parent_committee') }}
            </x-table.heading>
            <x-table.heading/>
            <x-table.heading/>
            <x-table.heading/>
        </x-slot>
        @forelse($committees as $committee)
            <x-table.row>
                <x-table.cell>{{ $committee->realm_uid }}</x-table.cell>
                <x-table.cell>{{ $committee->name }}</x-table.cell>
                <x-table.cell>
                    @if(empty($committee->parentCommittee))
                        {{ __('None') }}
                    @else
                        {{ $committee->parentCommittee->name }} ({{ $committee->parentCommittee->realm_uid }})
                    @endif
                </x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $committee->id }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
                <x-table.cell>
                    <x-button.link wire:click="edit('{{ $committee->id }}')">{{ __('Edit') }}</x-button.link>
                </x-table.cell>
                <x-table.cell>
                    <x-link href="{{ route('committees.roles', $committee->id) }}">{{ __('Roles') }}</x-link>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="3">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('committees.no_committees_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $committees->links() }}

    <form wire:submit.prevent="saveEdit">
        <x-modal.dialog wire:model.defer="showEditModal">
            <x-slot:title>
                {{ __('committees.edit', ['name' => $editCommitteeOldName]) }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="editCommittee.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
                <x-select wire:model="editCommittee.parent_committee_id" class="mt-2">
                    <x-slot:label>{{ __('committees.parent_committee') }}</x-slot:label>
                    <option value="please-select" selected="selected">{{ __('Please select') }}</option>
                    <option value="null">{{ __('None') }}</option>
                    @foreach($all_committees->where('realm_uid', (empty($editCommittee->realm_uid) || empty($editCommittee)) ? 'null' : $editCommittee->realm_uid)->where('id', '!=', (empty($editCommittee->id) || empty($editCommittee)) ? 'null' : $editCommittee->id) as $committee)
                        <option value="{{ $committee->id }}">{{ $committee->name }} ({{ $committee->realm_uid }})</option>
                    @endforeach
                </x-select>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>

    <form wire:submit.prevent="deleteCommit">
        <x-modal.confirmation wire:model.defer="showDeleteModal">
            <x-slot:title>
                {{ __('committees.delete_title', ['name' => $deleteCommitteeName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('committees.delete_warning', ['name' => $deleteCommitteeName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>

    <form wire:submit.prevent="saveNew">
        <x-modal.dialog wire:model.defer="showNewModal">
            <x-slot:title>
                {{ __('committees.new') }}
            </x-slot:title>
            <x-slot:content>
                <x-input.group wire:model="newCommittee.name">
                    <x-slot:label>{{ __('Name') }}</x-slot:label>
                </x-input.group>
                <x-select wire:model="newCommittee.realm_uid" class="mt-2">
                    <x-slot:label>Realm</x-slot:label>
                    <option value="please-select" selected="selected">{{ __('Please select') }}</option>
                    @foreach($realms as $realm)
                        <option value="{{ $realm->uid }}">{{ $realm->long_name }} ({{ $realm->uid }})</option>
                    @endforeach
                </x-select>
                <x-select wire:model="newCommittee.parent_committee_id" class="mt-2">
                    <x-slot:label>{{ __('committees.parent_committee') }}</x-slot:label>
                    <option value="please-select" selected="selected">{{ __('Please select') }}</option>
                    <option value="null">{{ __('None') }}</option>
                    @foreach($all_committees->where('realm_uid', (empty($newCommittee->realm_uid) || empty($newCommittee)) ? 'null' : $newCommittee->realm_uid)->where('id', '!=', (empty($newCommittee->id) || empty($newCommittee)) ? 'null' : $newCommittee->id) as $committee)
                        <option value="{{ $committee->id }}">{{ $committee->name }} ({{ $committee->realm_uid }})</option>
                    @endforeach
                </x-select>
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.primary type="submit">{{ __('Save') }}</x-button.primary>
            </x-slot:footer>
        </x-modal.dialog>
    </form>
</div>
