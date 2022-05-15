<x-auth-card>
    <x-slot name="logo"></x-slot>
    <form wire:submit.prevent="store">
        <!-- Email Address -->
        <x-input.group wire:model.lazy="user.email" autofocus>
            <x-slot name="label">Email</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        @error('domain') <small class="block text-red-500">{{ $message }}</small> @enderror
        <!-- Name -->
        <x-input.group wire:model="user.full_name">
            <x-slot name="label">Vollständiger Name</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <!-- Username -->
        <x-input.group wire:model="user.username">
            <x-slot name="label">Username</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <!-- Password -->
        <x-input.group wire:model.lazy="password" type="password">
            <x-slot name="label">Passwort</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <x-input.group wire:model="password_confirmation" type="password">
            <x-slot name="label">Passwort wiederholen</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <button>Submit</button>
    </form>
</x-auth-card>
