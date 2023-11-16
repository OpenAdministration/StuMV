<x-livewire-form>
    <x-slot:title>
        {{ __('roles.new') }}
    </x-slot:title>
    <x-input.group :label="__('Realm Name')" wire:model="uid" disabled/>
    <x-input.group :label="__('Short Rolename')" wire:model.live="cn"/>
    <x-input.group :label="__('Full Rolename')" wire:model="description"/>
    <x-slot:abort_route>
        {{ route('committees.roles', ['uid' => $uid, 'ou' => $ou]) }}
    </x-slot:abort_route>
</x-livewire-form>
