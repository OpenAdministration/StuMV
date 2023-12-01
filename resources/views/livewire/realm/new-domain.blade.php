<x-livewire-form>
    <x-slot:title>
        {{ __('domain.new') }}
    </x-slot:title>

    <x-input.group :label="__('Realm Name')" wire:model.live="uid" disabled/>
    <x-input.group :label="__('Domain FQDN')" wire:model.live="dc"/>
    <x-slot:abort_route>
        {{ route('realms.domains', ['uid' => $uid]) }}
    </x-slot:abort_route>
</x-livewire-form>
