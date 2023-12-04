<x-livewire-form>
    <x-slot:title>
        {{ __('committee.new') }}
    </x-slot:title>

    <x-input.group :label="__('Realm Name')" wire:model.live="realm_uid" disabled/>
    <x-select :label="__('Parent Committee')" wire:model.live="parent_dn">
        <option>{{ __('none') }}</option>
        @foreach($select_parents as $select_parent)
            <option value="{{ $select_parent->getDn() }}">{{ $select_parent->getFirstAttribute('description') }}</option>
        @endforeach
    </x-select>
    <x-input.group :label="__('Short Committee Name')" wire:model.live="ou"/>
    <x-input.group :label="__('Full Committee Name')" wire:model.live="description"/>
    <x-slot:abort_route>
        {{ route('committees.list', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
