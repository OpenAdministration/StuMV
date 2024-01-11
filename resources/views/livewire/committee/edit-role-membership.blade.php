<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{  __('roles.membership-edit_explanation', ['name' => $cn]) }}</h1>
            <p class="mt-2 text-sm text-gray-700">
                {{  __('roles.membership-edit_explanation', ['name' => $cn]) }}
            </p>
        </div>
    </div>

    <x-input.group :label="__('Short Rolename')" wire:model="cn" disabled />
    <x-input.group :label="__('Username')" wire:model="username" disabled />
    <x-input.group type="date" wire:model="start_date" :label="__('Starting')" />
    <x-input.group type="date" wire:model="end_date" :label="__('Ending')" />
    <x-input.group type="date" wire:model="decision_date" :label="__('Decided')" />
    <x-input.group wire:model="comment" :label="__('Comment')"/>

</x-livewire-form>
