<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('realms.add_members_to_role_heading') }}</h1>
        </div>
    </div>
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
</x-livewire-form>
