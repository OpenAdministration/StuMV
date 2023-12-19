<x-livewire-form>
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-base font-semibold leading-6 text-gray-900">{{ __('Change Password') }}</h1>
        </div>
    </div>
    <x-input.group type="password" wire:model="password" :label="__('Password')">
        <x-slot:help>{{ __('user.help.password') }}</x-slot:help>
    </x-input.group>
    <x-input.group type="password" wire:model="password_confirmation" :label="__('Password confirm')"/>
    <x-slot:abort_route>{{ back() }}</x-slot:abort_route>
</x-livewire-form>
