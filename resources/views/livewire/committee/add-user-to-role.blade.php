<x-livewire-form>
    <x-slot:title>
        {{ __('roles.new') }}
    </x-slot:title>

    <x-input.group :label="__('Realm Name')" wire:model="uid" disabled/>
    <x-input.group :label="__('Short Rolename')" wire:model="cn" disabled/>
    <x-select :label="__('Add new User')">
        @foreach($users as $user)
            <option value="{{ $user->getDn() }}">{{  $user }}</option>
        @endforeach
    </x-select>
    <x-slot:abort_route>
        {{ route('committees.roles', ['uid' => $uid, 'ou' => $ou]) }}
    </x-slot:abort_route>
</x-livewire-form>
