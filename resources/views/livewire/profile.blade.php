<div>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('Profile') }}</h1>
        </div>
    </div>
    <x-livewire-form :abort_route="null" wire:submit="save">
        <div class="mt-6 sm:mt-5">
            <x-input.group disabled :label="__('Username')" wire:model="uid">
                <x-slot:help>{{ __('validation.disabled', ['attribute' => 'username']) }}</x-slot:help>
            </x-input.group>
            <x-input.group disabled type="email" :label="__('E-Mail')" wire:model.live="email">
                <x-slot:help>{{ __('validation.disabled', ['attribute' => 'email']) }}</x-slot:help>
            </x-input.group>
            <x-input.group :label="__('Name')" wire:model.live="fullName"/>
        </div>
        <x-slot:abort_route>
            {{ url()->previous() }}
        </x-slot:abort_route>
    </x-livewire-form>
</div>
