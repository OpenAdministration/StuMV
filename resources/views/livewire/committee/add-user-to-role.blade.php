<x-livewire-form>
    <x-input.group :label="__('Realm Name')" wire:model="uid" disabled/>
    <x-input.group :label="__('Short Rolename')" wire:model="cn" disabled/>
    <x-select :label="__('Add new User')" wire:model="username">
        @foreach($users as $user)
            <option value="{{ $user->getFirstAttribute('uid') }}">{{ $user->getFirstAttribute('uid') }} ({{ $user->getFirstAttribute('cn') }})</option>
        @endforeach
    </x-select>
    <x-input.group type="date" wire:model="start_date" :label="__('Starting')"/>
    <x-input.group type="date" wire:model="end_date" :label="__('Ending')"/>
    <x-input.group type="date" wire:model="decision_date" :label="__('Decided')"/>
    <x-input.group wire:model="comment" :label="__('Comment')"/>
    <x-slot:abort_route>
        {{ route('committees.roles', ['uid' => $uid, 'ou' => $ou]) }}
    </x-slot:abort_route>
</x-livewire-form>
