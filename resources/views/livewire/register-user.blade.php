<x-auth-card>
    <x-slot name="logo"><x-application-logo class="w-20 h-20 fill-current text-gray-500"/></x-slot>
    <form wire:submit.prevent="store">
        <!-- Email Address -->
        <x-input.group wire:model.lazy="user.email" autofocus>
            <x-slot name="label">{{ __('Email') }}</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        @error('domain') <small class="block text-red-500">{{ $message }}</small> @enderror
        <!-- Name -->
        <x-input.group wire:model="user.full_name">
            <x-slot name="label">{{ __('Full name') }}</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <!-- Username -->
        <x-input.group wire:model="user.username">
            <x-slot name="label">{{ __('Username') }}</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <!-- Password -->
        <x-input.group wire:model.lazy="password" type="password">
            <x-slot name="label">{{ __('Password') }}</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <x-input.group wire:model="password_confirmation" type="password">
            <x-slot name="label">{{ __('Confirm Password') }}</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <x-button.primary type="submit" class="mt-2">Submit</x-button.primary>
    </form>
</x-auth-card>
