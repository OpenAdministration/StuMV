<div class="flex-col space-y-4">
    {{ Breadcrumbs::render('realms:members', $realm) }}
    <div class="flex justify-between">
        <x-input type="text" wire:model.debounce="search" placeholder="{{ __('realms.search_members') }}"></x-input>
        <x-button.primary class="flex" wire:click="new()"><x-fas-plus class="text-white align-middle"/>&nbsp;{{ __('New') }}</x-button.primary>
    </div>
    <x-table>
        <x-slot name="head">
            <x-table.heading
                sortable wire:click="sortBy('full_name')" :direction="$sortField === 'full_name' ? $sortDirection : null"
            >
                {{ __('Name') }}
            </x-table.heading>
            <x-table.heading
                sortable wire:click="sortBy('username')" :direction="$sortField === 'from' ? $sortDirection : null"
            >
                {{ __('Username') }}
            </x-table.heading>
            <x-table.heading/>
        </x-slot>
        @forelse($realm_members as $realm_member)
            <x-table.row>
                <x-table.cell>{{ $realm_member->full_name }}</x-table.cell>
                <x-table.cell>{{ $realm_member->username }}</x-table.cell>
                <x-table.cell>
                    <x-button.link-danger wire:click="deletePrepare('{{ $realm_member->id }}')">{{ __('Delete') }}</x-button.link-danger>
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.row>
                <x-table.cell colspan="4">
                    <div class="flex justify-center item-center">
                        <span class="text-gray-400 text-xl py-2 font-medium">{{ __('realms.no_members_found') }}</span>
                    </div>
                </x-table.cell>
            </x-table.row>
        @endforelse
    </x-table>
    {{ $realm_members->links() }}

    <form wire:submit.prevent="saveNew">
        <x-modal.dialog wire:model.defer="showNewModal">
            <x-slot:title>
                {{ __('realms.new_member') }}
            </x-slot:title>
            <x-slot:content>
                <x-select wire:model="newMember.id" class="mt-2">
                    <x-slot:label>{{ __('realms.new_member_label') }}</x-slot:label>
                    <option value="-1" selected="selected">{{ __('Please select') }}</option>
                    @foreach($free_members as $free_member)
                        <option value="{{ $free_member->id }}">{{ $free_member->full_name }} ({{ $free_member->username }})</option>
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
                {{ __('realms.delete_member_title', ['name' => $deleteMemberName]) }}
            </x-slot:title>
            <x-slot:content>
                {{ __('realms.delete_member_warning', ['name' => $deleteMemberName]) }}
            </x-slot:content>
            <x-slot:footer>
                <x-button.secondary wire:click="close()">{{ __('Cancel') }}</x-button.secondary>
                <x-button.danger type="submit">{{ __('Delete') }}</x-button.danger>
            </x-slot:footer>
        </x-modal.confirmation>
    </form>
</div>
