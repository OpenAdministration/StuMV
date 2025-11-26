<x-livewire-form>
    <flux:heading size="xl" class="mb-6">{{ __('New Committee') }}</flux:heading>
    <flux:field class="mb-6">
        <flux:label>{{ __('Parent Committee') }}</flux:label>
        <flux:select
            variant="listbox"
            searchable
            placeholder="{{ __('committees.select_committee') }}"
            wire:model="parent_dn"
        >
            <flux:select.option>{{ __('none') }}</flux:select.option>
            @foreach($select_parents as $select_parent)
                <flux:select.option value="{{ $select_parent->getDn() }}">{{ $select_parent->getFirstAttribute('description') }}</flux:select.option>
            @endforeach
        </flux:select>
    </flux:field>
    <flux:field class="mb-6">
        <flux:label>{{ __('Short Committee Name') }}</flux:label>
        <flux:description>{{ __('committees.new_hint_shortname') }}</flux:description>
        <flux:input type="text" wire:model.live="ou" required />
    </flux:field>
    <flux:field>
        <flux:label>{{ __('Full Committee Name') }}</flux:label>
        <flux:description>{{ __('committees.new_hint_longname') }}</flux:description>
        <flux:input type="text" wire:model.live="description" required />
    </flux:field>
    <x-slot:abort_route>
        {{ route('committees.list', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
