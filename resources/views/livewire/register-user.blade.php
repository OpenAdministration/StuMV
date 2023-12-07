<x-auth-card>
    <h2 class="font-bold text-gray-900 sm:truncate sm:tracking-tight">{{ __('user.register') }}</h2>
    <x-livewire-form>
        <!-- Email Address -->
        <x-input.group wire:model.blur="email" autofocus>
            <x-slot name="label">{{ __('Email') }}</x-slot>
            <x-slot name="help">{{ __('user.help.only_uni_mail') }}</x-slot>
        </x-input.group>
        @error('domain') <small class="block text-red-500">{{ $message }}</small> @enderror
        <!-- Name -->
        <x-input.group wire:model.blur="first_name">
            <x-slot name="label">{{ __('First name') }}</x-slot>
        </x-input.group>
        <x-input.group wire:model.blur="last_name">
            <x-slot name="label">{{ __('Last name') }}</x-slot>
        </x-input.group>
        <!-- Username -->
        <x-input.group wire:model.blur="username">
            <x-slot name="label">{{ __('Username') }}</x-slot>
            <x-slot name="help">{{ __('validation.alpha_dash', ['attribute' => __('Username')]) }}</x-slot>
        </x-input.group>
        <!-- Password -->
        <x-input.group wire:model.blur="password" type="password">
            <x-slot name="label">{{ __('Password') }}</x-slot>
            <x-slot name="help">{{ __('user.help.password') }}</x-slot>
        </x-input.group>
        <x-input.group wire:model.live="password_confirmation" type="password">
            <x-slot name="label">{{ __('Confirm Password') }}</x-slot>
            <x-slot name="help"></x-slot>
        </x-input.group>
        <x-slot:abort_route>{{ route('login') }}</x-slot:abort_route>
    </x-livewire-form>
</x-auth-card>
