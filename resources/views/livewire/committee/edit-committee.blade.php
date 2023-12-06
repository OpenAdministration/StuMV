<x-livewire-form>
    <x-slot:title>
        {{ __('committee.new') }}
    </x-slot:title>

    <x-input.group :label="__('Realm Name')" wire:model.live="realm_uid" disabled/>
    <x-input.group :label="__('Parent Committee')" wire:model.live="parent_ou" :placeholder="__('None')" disabled/>
    <x-input.group :label="__('Short Committee Name')" wire:model.live="ou" disabled/>
    <x-input.group :label="__('Full Committee Name')" wire:model.live="description"/>
    <x-slot:abort_route>
        {{ route('committees.list', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
