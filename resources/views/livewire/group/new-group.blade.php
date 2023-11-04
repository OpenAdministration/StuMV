<x-livewire-form>
    <x-slot:title>
        {{ __('groups.new') }}
    </x-slot:title>

    <x-input.group :label="__('Realm Name')" wire:model="realm_uid" disabled/>
    <x-input.group :label="__('Short Groupname')" wire:model="cn"/>
    <x-input.group :label="__('Full Groupname')" wire:model="name"/>
    <x-slot:abort_route>
        {{ route('realms.groups', ['uid' => $realm_uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
