<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{  __('roles.edit_heading', ['name' => $cn]) }}</h1>
        </div>
    </div>

    <x-input.group :label="__('Short Rolename')" wire:model="cn" disabled />
    <x-input.group wire:model="description" :label="__('Full Name')"/>

</x-livewire-form>
